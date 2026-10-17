<?php

namespace Tests\Feature;

use App\Jobs\CaptureSnapshotsBatch;
use App\Jobs\ScanStatement;
use App\Jobs\SendRemindersBatch;
use App\Models\Lender;
use App\Notifications\PaymentReminder;
use Database\Seeders\LenderSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ScalabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_idempotency_key_replays_instead_of_creating_twice(): void
    {
        $headers = $this->authHeaders($this->createUser()) + ['Idempotency-Key' => 'add-card-0001'];
        $card = ['bankName' => 'HDFC Bank', 'cardName' => 'Millennia', 'creditLimitRupees' => 100000, 'aprPercent' => 42];

        $first = $this->postJson('/api/v1/credit-cards', $card, $headers)->assertCreated();
        $retry = $this->postJson('/api/v1/credit-cards', $card, $headers)->assertCreated()->assertHeader('Idempotent-Replayed', 'true');

        $this->assertSame($first->json('data.id'), $retry->json('data.id'));
        $this->assertSame(1, DB::table('credit_cards')->count());

        // Same key, different request
        $this->postJson('/api/v1/credit-cards', [...$card, 'cardName' => 'Regalia'], $headers)->assertStatus(422);
    }

    public function test_idempotency_keys_are_per_customer(): void
    {
        $card = ['bankName' => 'HDFC Bank', 'cardName' => 'Millennia', 'creditLimitRupees' => 100000, 'aprPercent' => 42];

        $this->postJson('/api/v1/credit-cards', $card, $this->authHeaders($this->createUser()) + ['Idempotency-Key' => 'shared-key-1'])->assertCreated();
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/credit-cards', $card, $this->authHeaders($this->createUser()) + ['Idempotency-Key' => 'shared-key-1'])
            ->assertCreated()
            ->assertHeaderMissing('Idempotent-Replayed');

        $this->assertSame(2, DB::table('credit_cards')->count());
    }

    public function test_cached_dashboard_refreshes_when_data_changes(): void
    {
        $user = $this->createUser();
        $headers = $this->authHeaders($user);
        $user->creditCards()->create(['bank_name' => 'HDFC Bank', 'card_name' => 'Millennia', 'credit_limit_paise' => 10000000, 'outstanding_paise' => 5000000, 'apr_bps' => 4200]);

        $this->getJson('/api/v1/dashboard/summary', $headers)->assertJsonPath('data.totalOutstandingRupees', 50000);

        // Served from cache: a raw DB change that bypasses the model is not seen...
        DB::table('credit_cards')->update(['outstanding_paise' => 1]);
        $this->getJson('/api/v1/dashboard/summary', $headers)->assertJsonPath('data.totalOutstandingRupees', 50000);

        // ...but any change through the app invalidates it
        $user->creditCards()->first()->update(['outstanding_paise' => 3000000]);
        $this->getJson('/api/v1/dashboard/summary', $headers)->assertJsonPath('data.totalOutstandingRupees', 30000);
    }

    public function test_lender_catalogue_cache_is_cleared_by_admin_edits(): void
    {
        $this->seed(LenderSeeder::class);

        $this->getJson('/api/v1/lenders')->assertJsonPath('data.0.name', 'HDFC Bank');

        Lender::where('name', 'Muthoot Finance')->first()->update(['min_rate_bps' => 700]);

        $this->getJson('/api/v1/lenders')->assertJsonPath('data.0.name', 'Muthoot Finance');
    }

    public function test_nightly_work_is_split_into_batches(): void
    {
        Queue::fake();
        foreach (range(1, 3) as $i) {
            $this->createUser();
        }

        $this->artisan('navesta:capture-snapshots')->expectsOutputToContain('Queued snapshots for 3 customers in 1 batches')->assertSuccessful();

        Queue::assertPushed(CaptureSnapshotsBatch::class, fn (CaptureSnapshotsBatch $job) => count($job->userIds) === 3);
    }

    public function test_jobs_use_dedicated_queues(): void
    {
        Queue::fake();
        Storage::fake('statements');
        $user = $this->createUser();

        $this->post('/api/v1/statements', ['file' => UploadedFile::fake()->createWithContent('s.csv', "a\n1\n")], $this->authHeaders($user) + ['Accept' => 'application/json'])->assertCreated();

        Queue::assertPushedOn('scans', ScanStatement::class);
        $this->assertSame('notifications', (new PaymentReminder('sms', 'x', 1, 'y'))->queue);
        $this->assertNull((new SendRemindersBatch([], '2026-11-15'))->queue);
    }

    public function test_api_rate_limit_is_per_customer_not_per_ip(): void
    {
        config(['navesta.api_rate_limit' => 3]);
        $a = $this->authHeaders($this->createUser());
        $b = $this->authHeaders($this->createUser());

        foreach (range(1, 3) as $i) {
            $this->app['auth']->forgetGuards();
            $this->getJson('/api/v1/credit-cards', $a)->assertOk();
        }
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/credit-cards', $a)->assertStatus(429);

        // Same IP, different customer: unaffected
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/credit-cards', $b)->assertOk();
    }
}
