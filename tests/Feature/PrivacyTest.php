<?php

namespace Tests\Feature;

use App\Contracts\MessageGateway;
use App\Models\AuditLog;
use App\Models\Statement;
use App\Models\User;
use App\Models\UserFootprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PrivacyTest extends TestCase
{
    use RefreshDatabase;

    private function register(array $overrides = [])
    {
        return $this->postJson('/api/v1/auth/register', [
            'name' => 'Asha Rao',
            'email' => 'asha@example.com',
            'password' => 'secret123',
            'acceptTerms' => true,
            ...$overrides,
        ]);
    }

    public function test_sign_up_requires_explicit_consent(): void
    {
        $this->register(['acceptTerms' => false])
            ->assertStatus(400)
            ->assertJsonPath('error', 'Please accept the terms and privacy policy to continue');
    }

    public function test_consents_are_recorded_with_notice_versions(): void
    {
        $this->register()->assertCreated();
        $user = User::firstWhere('email', 'asha@example.com');

        $this->assertFalse($user->reminders_enabled); // reminders are opt-in
        $this->assertSame(['privacy', 'reminders', 'terms'], $user->consents()->orderBy('purpose')->pluck('purpose')->all());
        $this->assertDatabaseHas('consents', ['user_id' => $user->id, 'purpose' => 'terms', 'granted' => true, 'notice_version' => '2026-10']);
        $this->assertDatabaseHas('consents', ['user_id' => $user->id, 'purpose' => 'reminders', 'granted' => false]);
    }

    public function test_reminder_opt_in_and_withdrawal_are_recorded(): void
    {
        $this->register(['acceptReminders' => true])->assertCreated();
        $user = User::firstWhere('email', 'asha@example.com');
        $this->assertTrue($user->reminders_enabled);

        $this->patchJson('/api/v1/users/me', ['remindersEnabled' => false], $this->authHeaders($user))->assertOk();

        $this->getJson('/api/v1/users/me', $this->authHeaders($user))
            ->assertJsonPath('data.consents.reminders.granted', false);
        $this->assertSame(2, $user->consents()->where('purpose', 'reminders')->count());
    }

    public function test_export_contains_all_personal_data(): void
    {
        $user = $this->createUser(['phone' => '+919812345678']);
        $user->creditCards()->create(['bank_name' => 'HDFC Bank', 'card_name' => 'Millennia', 'credit_limit_paise' => 100000, 'outstanding_paise' => 5000, 'apr_bps' => 4200]);
        $user->creditScores()->create(['score' => 750, 'fetched_at' => now()]);

        $response = $this->get('/api/v1/users/me/export', $this->authHeaders($user) + ['Accept' => 'application/json']);
        $response->assertOk()->assertHeader('Content-Disposition');

        $data = json_decode($response->streamedContent(), true);
        $this->assertSame('+919812345678', $data['profile']['phone']);
        $this->assertCount(1, $data['creditCards']);
        $this->assertSame(750, $data['creditScores'][0]['score']);
        $this->assertArrayHasKey('activity', $data);
        $this->assertSame(1, AuditLog::where('action', 'account.exported')->count());
    }

    public function test_account_deletion_erases_data_and_files(): void
    {
        Storage::fake('statements');
        $user = $this->createUser();
        $headers = $this->authHeaders($user);
        $user->creditCards()->create(['bank_name' => 'HDFC Bank', 'card_name' => 'Millennia', 'credit_limit_paise' => 100000, 'outstanding_paise' => 5000, 'apr_bps' => 4200]);
        $this->post('/api/v1/statements', ['file' => UploadedFile::fake()->createWithContent('s.csv', "a,b\n1,2\n")], $headers + ['Accept' => 'application/json'])->assertCreated();

        $this->deleteJson('/api/v1/users/me', ['password' => 'wrong'], $headers)->assertStatus(400);
        $this->deleteJson('/api/v1/users/me', ['password' => 'secret123'], $headers)->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('credit_cards', ['user_id' => $user->id]);
        $this->assertSame([], Storage::disk('statements')->allFiles());

        // The deletion itself is on record, without the email address
        $entry = AuditLog::where('action', 'account.deleted')->first();
        $this->assertSame($user->id, $entry->actor_id);
        $this->assertStringNotContainsString($user->email, json_encode($entry->metadata));
    }

    public function test_debt_guidance_and_marketing_consents_are_recorded_with_history(): void
    {
        $user = $this->createUser();
        $headers = $this->authHeaders($user);

        $this->postJson('/api/v1/users/me/consents', ['purpose' => 'debt_guidance', 'granted' => true], $headers)
            ->assertOk()
            ->assertJsonPath('data.consents.debt_guidance.granted', true)
            ->assertJsonPath('data.consents.debt_guidance.noticeVersion', '2026-11');
        $this->postJson('/api/v1/users/me/consents', ['purpose' => 'marketing', 'granted' => false], $headers)->assertOk();
        $this->postJson('/api/v1/users/me/consents', ['purpose' => 'debt_guidance', 'granted' => false], $headers)
            ->assertJsonPath('data.consents.debt_guidance.granted', false);

        // Every change is kept, and terms/privacy can't be withdrawn this way
        $this->assertSame(2, $user->consents()->where('purpose', 'debt_guidance')->count());
        $this->postJson('/api/v1/users/me/consents', ['purpose' => 'terms', 'granted' => false], $headers)->assertStatus(400);
        $this->assertSame(1, AuditLog::where('action', 'consent.withdrawn')->where('actor_id', $user->id)->where('metadata->purpose', 'debt_guidance')->count());
    }

    public function test_account_deletion_leaves_nothing_behind(): void
    {
        $user = $this->createUser();
        $headers = $this->authHeaders($user);
        $card = $user->creditCards()->create(['bank_name' => 'HDFC Bank', 'card_name' => 'Millennia', 'credit_limit_paise' => 100000, 'outstanding_paise' => 5000, 'apr_bps' => 4200]);
        $loan = $user->loans()->create(['lender_name' => 'SBI', 'loan_type' => 'personal', 'principal_paise' => 10000000, 'outstanding_paise' => 8000000, 'interest_rate_bps' => 1400, 'emi_paise' => 341804, 'tenure_months' => 36, 'tenure_remaining_months' => 24]);
        UserFootprint::create(['user_id' => $user->id, 'event_type' => 'login', 'ip_address' => '203.0.113.7']);

        $this->deleteJson('/api/v1/users/me', ['password' => 'secret123'], $headers)->assertOk();

        // Erased outright, not hidden behind a "deleted" flag
        foreach (['users' => $user->id, 'credit_cards' => $card->id, 'loans' => $loan->id] as $table => $id) {
            $this->assertSame(0, DB::table($table)->where('id', $id)->count(), "{$table} row still exists");
        }
        $this->assertSame(0, DB::table('user_footprints')->where('user_id', $user->id)->count());
        $this->assertSame(0, DB::table('consents')->where('user_id', $user->id)->count());
        $this->assertSame(0, DB::table('personal_access_tokens')->where('tokenable_id', $user->id)->count());
    }

    public function test_phone_only_accounts_confirm_deletion_with_an_sms_code(): void
    {
        $sent = [];
        $this->app->instance(MessageGateway::class, new class($sent) implements MessageGateway
        {
            public function __construct(private array &$sent) {}

            public function send(string $channel, string $to, string $message): string
            {
                $this->sent[] = $message;

                return 'fake';
            }
        });

        // Signed up with mobile + OTP: stand-in email, random password
        $user = $this->createUser(['email' => 'user_abc'.User::PHONE_ONLY_EMAIL_DOMAIN, 'password_hash' => Hash::make(Str::random(40))]);
        $user->forceFill(['phone_verified_at' => now()])->save();
        $headers = $this->authHeaders($user);

        $this->getJson('/api/v1/users/me', $headers)->assertJsonPath('data.hasPassword', false);

        $this->postJson('/api/v1/users/me/delete-code', [], $headers)->assertOk()->assertJsonStructure(['data' => ['phoneHint']]);
        preg_match('/(\d{6}) is your Navesta/', end($sent), $m);

        $this->deleteJson('/api/v1/users/me', ['code' => $m[1] === '000000' ? '111111' : '000000'], $headers)->assertStatus(400);
        $this->assertDatabaseHas('users', ['id' => $user->id]);

        $this->deleteJson('/api/v1/users/me', ['code' => $m[1]], $headers)->assertOk();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_deletion_needs_a_password_or_a_code(): void
    {
        $this->deleteJson('/api/v1/users/me', [], $this->authHeaders($this->createUser()))->assertStatus(400);
    }

    public function test_admins_cannot_delete_their_own_account_through_the_api(): void
    {
        $admin = $this->createAdmin();

        $this->deleteJson('/api/v1/users/me', ['password' => 'secret123'], $this->authHeaders($admin))->assertStatus(400);
    }

    public function test_old_audit_entries_and_statements_are_pruned(): void
    {
        Storage::fake('statements');
        $user = $this->createUser();

        $this->travel(-800)->days();
        AuditLog::create(['actor_role' => 'system', 'action' => 'old.entry']);
        Storage::disk('statements')->put('old.enc', 'x');
        $old = $user->statements()->create(['original_name' => 'old.csv', 'disk' => 'statements', 'path' => 'old.enc', 'mime_type' => 'text/csv', 'size_bytes' => 1, 'sha256' => str_repeat('0', 64)]);
        $this->travelBack();

        $this->artisan('model:prune', ['--model' => [AuditLog::class, Statement::class]])->assertSuccessful();

        $this->assertDatabaseMissing('audit_logs', ['action' => 'old.entry']);
        $this->assertDatabaseMissing('statements', ['id' => $old->id]);
        $this->assertFalse(Storage::disk('statements')->exists('old.enc'));
    }
}
