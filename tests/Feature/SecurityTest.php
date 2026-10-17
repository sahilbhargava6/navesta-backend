<?php

namespace Tests\Feature;

use App\Contracts\MessageGateway;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    /** Messages "sent" by the fake gateway. */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();

        $sent = &$this->sent;
        $this->app->instance(MessageGateway::class, new class($sent) implements MessageGateway
        {
            public function __construct(private array &$sent) {}

            public function send(string $channel, string $to, string $message): string
            {
                $this->sent[] = compact('channel', 'to', 'message');

                return 'fake-'.count($this->sent);
            }
        });
    }

    private function lastCode(): string
    {
        preg_match('/^(\d{6}) is your Navesta verification code/', end($this->sent)['message'], $m);

        return $m[1];
    }

    private const WEBSITE = ['Referer' => 'http://localhost:3000/', 'Origin' => 'http://localhost:3000'];

    public function test_website_sign_in_uses_an_http_only_session_cookie_and_no_token(): void
    {
        $this->createUser(['email' => 'asha@example.com']);

        $login = $this->postJson('/api/v1/auth/login', ['email' => 'asha@example.com', 'password' => 'secret123'], self::WEBSITE)
            ->assertOk()
            ->assertJsonPath('data.token', null);

        $cookie = $login->getCookie(config('session.cookie'));
        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isHttpOnly());

        $this->app['auth']->forgetGuards();
        $this->withCookie(config('session.cookie'), $cookie->getValue())
            ->getJson('/api/v1/users/me', self::WEBSITE)
            ->assertOk()
            ->assertJsonPath('data.email', 'asha@example.com');
    }

    public function test_other_clients_still_get_a_bearer_token(): void
    {
        $this->createUser(['email' => 'asha@example.com']);

        $token = $this->postJson('/api/v1/auth/login', ['email' => 'asha@example.com', 'password' => 'secret123'])
            ->assertOk()
            ->json('data.token');

        $this->assertIsString($token);
        $this->getJson('/api/v1/users/me', ['Authorization' => "Bearer {$token}"])->assertOk();
    }

    public function test_repeated_wrong_passwords_lock_the_email(): void
    {
        $this->createUser(['email' => 'asha@example.com']);

        foreach (range(1, 10) as $i) {
            $this->postJson('/api/v1/auth/login', ['email' => 'asha@example.com', 'password' => 'wrong'], ['REMOTE_ADDR' => "10.0.0.{$i}"])->assertStatus(401);
        }

        // Even the right password is refused while locked, from any IP
        $this->postJson('/api/v1/auth/login', ['email' => 'asha@example.com', 'password' => 'secret123'], ['REMOTE_ADDR' => '10.0.1.1'])
            ->assertStatus(429)
            ->assertJsonPath('error', fn (string $e) => str_starts_with($e, 'Too many failed sign-in attempts'));

        $this->assertSame(10, AuditLog::where('action', 'auth.login.failed')->count());
        $this->assertSame(1, AuditLog::where('action', 'auth.login.locked')->count());
    }

    public function test_sign_in_attempts_are_rate_limited_per_ip(): void
    {
        foreach (range(1, 10) as $i) {
            $this->postJson('/api/v1/auth/login', ['email' => "nobody{$i}@example.com", 'password' => 'x']);
        }

        $this->postJson('/api/v1/auth/login', ['email' => 'another@example.com', 'password' => 'x'])->assertStatus(429);
    }

    public function test_weak_passwords_are_rejected(): void
    {
        $base = ['name' => 'Asha Rao', 'email' => 'asha@example.com', 'acceptTerms' => true];

        $this->postJson('/api/v1/auth/register', [...$base, 'password' => 'short1'])->assertStatus(400);
        $this->postJson('/api/v1/auth/register', [...$base, 'password' => 'onlyletters'])->assertStatus(400);
        $this->postJson('/api/v1/auth/register', [...$base, 'password' => '1234567890'])->assertStatus(400);
        $this->postJson('/api/v1/auth/register', [...$base, 'password' => 'letters123'])->assertCreated();
    }

    public function test_phone_is_encrypted_at_rest(): void
    {
        $user = $this->createUser(['phone' => '+919812345678']);

        $raw = DB::table('users')->where('id', $user->id)->value('phone');

        $this->assertStringNotContainsString('9812345678', $raw);
        $this->assertSame('+919812345678', $user->fresh()->phone);
    }

    public function test_phone_verification_by_sms_code(): void
    {
        $user = $this->createUser(['phone' => '+919812345678']);
        $headers = $this->authHeaders($user);

        $this->postJson('/api/v1/users/me/phone/send-code', [], $headers)
            ->assertOk()
            ->assertJsonPath('data.phoneHint', '+91******5678');
        $this->assertSame('+919812345678', end($this->sent)['to']);

        $this->postJson('/api/v1/users/me/phone/verify', ['code' => '000000'], $headers)->assertStatus(400);
        $this->postJson('/api/v1/users/me/phone/verify', ['code' => $this->lastCode()], $headers)
            ->assertOk()
            ->assertJsonPath('data.phoneVerified', true);

        $this->assertNotNull($user->fresh()->phone_verified_at);
    }

    public function test_a_code_is_locked_after_five_wrong_guesses(): void
    {
        $user = $this->createUser();
        $headers = $this->authHeaders($user);

        $this->postJson('/api/v1/users/me/phone/send-code', [], $headers)->assertOk();
        $code = $this->lastCode();

        foreach (range(1, 5) as $i) {
            $this->postJson('/api/v1/users/me/phone/verify', ['code' => $code === '111111' ? '222222' : '111111'], $headers)->assertStatus(400);
        }

        // The right code no longer works
        $this->postJson('/api/v1/users/me/phone/verify', ['code' => $code], $headers)->assertStatus(400);
    }

    public function test_sms_sign_in_code_when_two_factor_is_on(): void
    {
        $user = $this->createUser(['email' => 'asha@example.com', 'phone' => '+919812345678']);

        // Requires a verified phone first
        $this->patchJson('/api/v1/users/me', ['twoFactorEnabled' => true], $this->authHeaders($user))->assertStatus(400);
        $user->forceFill(['phone_verified_at' => now()])->save();
        $this->app['auth']->forgetGuards();
        $this->patchJson('/api/v1/users/me', ['twoFactorEnabled' => true], $this->authHeaders($user))->assertOk();

        $step1 = $this->postJson('/api/v1/auth/login', ['email' => 'asha@example.com', 'password' => 'secret123'])
            ->assertOk()
            ->assertJsonPath('data.requiresOtp', true)
            ->assertJsonMissingPath('data.token');

        $challenge = $step1->json('data.challengeId');

        $this->postJson('/api/v1/auth/login/verify-otp', ['challengeId' => $challenge, 'code' => '000000'])->assertStatus(401);

        $token = $this->postJson('/api/v1/auth/login/verify-otp', ['challengeId' => $challenge, 'code' => $this->lastCode()])
            ->assertOk()
            ->json('data.token');
        $this->assertIsString($token);

        // A challenge works once
        $this->postJson('/api/v1/auth/login/verify-otp', ['challengeId' => $challenge, 'code' => $this->lastCode()])->assertStatus(401);
    }

    public function test_changing_phone_requires_verifying_again(): void
    {
        $user = $this->createUser(['phone' => '+919812345678', 'two_factor_enabled' => true]);
        $user->forceFill(['phone_verified_at' => now()])->save();

        $this->patchJson('/api/v1/users/me', ['phone' => '+91 98000 11111'], $this->authHeaders($user))
            ->assertOk()
            ->assertJsonPath('data.phone', '+919800011111')
            ->assertJsonPath('data.phoneVerified', false)
            ->assertJsonPath('data.twoFactorEnabled', false);
    }

    public function test_actions_are_audited_without_secrets(): void
    {
        $user = $this->createUser(['email' => 'asha@example.com']);
        $this->postJson('/api/v1/auth/login', ['email' => 'asha@example.com', 'password' => 'secret123'])->assertOk();
        $headers = $this->authHeaders($user);

        $this->postJson('/api/v1/credit-cards', ['bankName' => 'HDFC Bank', 'cardName' => 'Millennia', 'creditLimitRupees' => 100000, 'aprPercent' => 42], $headers)->assertCreated();
        $this->patchJson('/api/v1/users/me', ['phone' => '+919800011111'], $headers)->assertOk();

        $login = AuditLog::where('action', 'auth.login.succeeded')->first();
        $this->assertSame($user->id, $login->actor_id);
        $this->assertSame('customer', $login->actor_role);

        $card = AuditLog::where('action', 'credit_card.created')->first();
        $this->assertSame($user->id, $card->actor_id);
        $this->assertSame('active', $card->metadata['status']);

        $profile = AuditLog::where('action', 'user.updated')->latest('id')->first();
        $this->assertContains('phone', $profile->metadata['fields']);
        $this->assertStringNotContainsString('9800011111', json_encode($profile->metadata));
    }

    public function test_audit_entries_cannot_be_edited(): void
    {
        $this->createUser();
        $entry = AuditLog::first();

        $this->expectException(\LogicException::class);
        $entry->update(['action' => 'tampered']);
    }
}
