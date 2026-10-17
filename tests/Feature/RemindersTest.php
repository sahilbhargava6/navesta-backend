<?php

namespace Tests\Feature;

use App\Notifications\PaymentReminder;
use App\Services\Messaging\TwilioGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RemindersTest extends TestCase
{
    use RefreshDatabase;

    private function reminderPayload(array $overrides = []): array
    {
        return ['channel' => 'sms', 'accountName' => 'HDFC Millennia', 'amountRupees' => 6250, 'dueDate' => '15th November', ...$overrides];
    }

    public function test_reminder_goes_to_the_registered_phone_only(): void
    {
        Notification::fake();
        $user = $this->createUser(['phone' => '+919811111111']);

        $this->postJson('/api/v1/notifications/reminder', $this->reminderPayload(['phone' => '+910000000000']), $this->authHeaders($user))
            ->assertStatus(202)
            ->assertJsonPath('payload.recipient', '+919811111111');

        Notification::assertSentTo($user, PaymentReminder::class, fn (PaymentReminder $n) => $n->channel === 'sms' && $n->amountRupees === 6250);
        Notification::assertCount(1);
    }

    public function test_reminder_requires_a_phone_on_the_account(): void
    {
        Notification::fake();
        $user = $this->createUser(['phone' => null]);

        $this->postJson('/api/v1/notifications/reminder', $this->reminderPayload(), $this->authHeaders($user))
            ->assertStatus(400)
            ->assertJsonPath('error', 'Add a phone number to your profile to receive reminders');

        Notification::assertNothingSent();
    }

    public function test_reminders_require_login_and_are_rate_limited(): void
    {
        Notification::fake();
        $this->postJson('/api/v1/notifications/reminder', $this->reminderPayload())->assertStatus(401);

        $headers = $this->authHeaders($this->createUser());
        foreach (range(1, 5) as $i) {
            $this->postJson('/api/v1/notifications/reminder', $this->reminderPayload(), $headers)->assertStatus(202);
        }

        $this->postJson('/api/v1/notifications/reminder', $this->reminderPayload(), $headers)->assertStatus(429);
    }

    public function test_scheduled_command_reminds_about_payments_due_in_three_days(): void
    {
        Notification::fake();
        Carbon::setTestNow('2026-11-12 03:30:00'); // 09:00 IST on 12 Nov -> due 15 Nov

        $dueSoon = $this->createUser();
        $dueSoon->creditCards()->create(['bank_name' => 'HDFC Bank', 'card_name' => 'Millennia', 'credit_limit_paise' => 25000000, 'outstanding_paise' => 12500000, 'apr_bps' => 4200, 'min_payment_paise' => 625000, 'due_day' => 15]);
        $dueSoon->loans()->create(['lender_name' => 'Bajaj Finserv', 'loan_type' => 'personal', 'principal_paise' => 50000000, 'outstanding_paise' => 38000000, 'interest_rate_bps' => 1450, 'emi_paise' => 1725000, 'tenure_months' => 36, 'tenure_remaining_months' => 24, 'next_due_date' => '2026-11-15']);

        $notDue = $this->createUser();
        $notDue->creditCards()->create(['bank_name' => 'SBI Card', 'card_name' => 'Prime', 'credit_limit_paise' => 10000000, 'outstanding_paise' => 100000, 'apr_bps' => 3600, 'due_day' => 20]);

        $optedOut = $this->createUser(['reminders_enabled' => false]);
        $optedOut->creditCards()->create(['bank_name' => 'Axis', 'card_name' => 'Ace', 'credit_limit_paise' => 10000000, 'outstanding_paise' => 100000, 'apr_bps' => 3600, 'due_day' => 15]);

        $this->artisan('navesta:send-due-reminders')->expectsOutputToContain('Queued reminders for 1 customers (1 batches)')->assertSuccessful();

        Notification::assertSentToTimes($dueSoon, PaymentReminder::class, 2);
        Notification::assertSentTo($dueSoon, PaymentReminder::class, fn (PaymentReminder $n) => $n->amountRupees === 6250 && $n->dueDate === '15th November');
        Notification::assertNotSentTo([$notDue, $optedOut], PaymentReminder::class);
    }

    public function test_customers_can_turn_reminders_off(): void
    {
        $user = $this->createUser();

        $this->patchJson('/api/v1/users/me', ['remindersEnabled' => false], $this->authHeaders($user))
            ->assertOk()
            ->assertJsonPath('data.remindersEnabled', false);

        $this->assertFalse($user->fresh()->reminders_enabled);
    }

    public function test_twilio_gateway_sends_whatsapp_through_the_messages_api(): void
    {
        Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM123'], 201)]);

        $sid = (new TwilioGateway('AC1', 'secret', '+15550001', '+15550002'))->send('whatsapp', '+919800000000', 'Hello');

        $this->assertSame('SM123', $sid);
        Http::assertSent(fn (HttpRequest $r) => $r->url() === 'https://api.twilio.com/2010-04-01/Accounts/AC1/Messages.json'
            && $r['To'] === 'whatsapp:+919800000000'
            && $r['From'] === 'whatsapp:+15550002'
            && $r['Body'] === 'Hello'
            && $r->hasHeader('Authorization', 'Basic '.base64_encode('AC1:secret')));
    }

    public function test_twilio_errors_are_raised_so_the_queue_retries(): void
    {
        Http::fake(['api.twilio.com/*' => Http::response(['message' => 'Invalid To number'], 400)]);

        $this->expectExceptionMessage('Invalid To number');

        (new TwilioGateway('AC1', 'secret', '+15550001', null))->send('sms', 'bad', 'Hello');
    }
}
