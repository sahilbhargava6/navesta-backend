<?php

namespace Tests\Feature;

use App\Contracts\MessageGateway;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mobile number + SMS code sign-up and sign-in (the website's auth flow).
 */
class PhoneAuthTest extends TestCase
{
    use RefreshDatabase;

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
                $this->sent[] = compact('to', 'message');

                return 'fake';
            }
        });
    }

    private function requestCode(string $phone): array
    {
        $challenge = $this->postJson('/api/v1/auth/phone/send-otp', ['phone' => $phone])->assertOk()->json('data.challengeId');
        preg_match('/code is (\d{6})/', end($this->sent)['message'], $m);

        return [$challenge, $m[1]];
    }

    public function test_sign_up_with_mobile_and_code_records_consent(): void
    {
        [$challenge, $code] = $this->requestCode('98765 43210');
        $this->assertSame('+919876543210', end($this->sent)['to']);

        $this->postJson('/api/v1/auth/phone/verify-otp', [
            'challengeId' => $challenge,
            'code' => $code,
            'name' => 'Rahul Mehta',
            'acceptTerms' => true,
            'acceptMarketing' => false,
        ])->assertOk()->assertJsonPath('data.user.name', 'Rahul Mehta');

        $user = User::findByPhone('9876543210');
        $this->assertNotNull($user);
        $this->assertNotNull($user->phone_verified_at);
        $this->assertSame(['marketing', 'privacy', 'reminders', 'terms'], $user->consents()->orderBy('purpose')->pluck('purpose')->all());
        $this->assertDatabaseHas('consents', ['user_id' => $user->id, 'purpose' => 'marketing', 'granted' => false]);
    }

    public function test_unknown_number_cannot_sign_in_without_signing_up(): void
    {
        [$challenge, $code] = $this->requestCode('9876543210');

        $this->postJson('/api/v1/auth/phone/verify-otp', ['challengeId' => $challenge, 'code' => $code])
            ->assertStatus(404)
            ->assertJsonPath('error', 'No Navesta account uses this number yet. Create a free account to continue.');

        $this->assertSame(0, User::count());
    }

    public function test_existing_customer_signs_in_with_any_number_format(): void
    {
        $user = $this->createUser(['phone' => '+919812345678']);
        [$challenge, $code] = $this->requestCode('919812345678');

        $this->postJson('/api/v1/auth/phone/verify-otp', ['challengeId' => $challenge, 'code' => $code])
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id);
    }

    public function test_wrong_codes_are_limited_and_codes_work_once(): void
    {
        $this->createUser(['phone' => '+919812345678']);
        [$challenge, $code] = $this->requestCode('9812345678');
        $wrong = $code === '000000' ? '111111' : '000000';

        foreach (range(1, 4) as $i) {
            $this->postJson('/api/v1/auth/phone/verify-otp', ['challengeId' => $challenge, 'code' => $wrong])->assertStatus(401);
        }
        $this->postJson('/api/v1/auth/phone/verify-otp', ['challengeId' => $challenge, 'code' => $wrong])
            ->assertStatus(401)
            ->assertJsonPath('error', 'Too many incorrect codes. Please request a new one.');

        // The right code no longer works after the lockout
        $this->postJson('/api/v1/auth/phone/verify-otp', ['challengeId' => $challenge, 'code' => $code])->assertStatus(401);
    }

    public function test_test_code_needs_a_real_request(): void
    {
        // 123456 is accepted in testing only for a code that was actually requested
        $this->postJson('/api/v1/auth/phone/verify-otp', ['challengeId' => str_repeat('a', 40), 'code' => '123456'])->assertStatus(401);
    }

    public function test_phone_hash_follows_phone_changes(): void
    {
        $user = $this->createUser(['phone' => '+919812345678']);
        $user->update(['phone' => '+919800011111']);

        $this->assertNull(User::findByPhone('9812345678'));
        $this->assertTrue(User::findByPhone('+91 98000 11111')->is($user));
    }
}
