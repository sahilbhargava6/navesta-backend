<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function seedDebts(User $user): void
    {
        $user->creditCards()->create(['bank_name' => 'HDFC Bank', 'card_name' => 'Millennia', 'credit_limit_paise' => 25000000, 'outstanding_paise' => 12500000, 'apr_bps' => 4200, 'min_payment_paise' => 625000]);
        $user->creditCards()->create(['bank_name' => 'SBI Card', 'card_name' => 'SimplyCLICK', 'credit_limit_paise' => 15000000, 'outstanding_paise' => 6500000, 'apr_bps' => 3900, 'min_payment_paise' => 325000]);
        $user->loans()->create(['lender_name' => 'Bajaj Finserv', 'loan_type' => 'personal', 'principal_paise' => 50000000, 'outstanding_paise' => 38000000, 'interest_rate_bps' => 1450, 'emi_paise' => 1725000, 'tenure_months' => 36, 'tenure_remaining_months' => 24]);
    }

    public function test_summary_totals(): void
    {
        $user = $this->createUser();
        $this->seedDebts($user);

        $this->getJson('/api/v1/dashboard/summary', $this->authHeaders($user))
            ->assertOk()
            ->assertJsonPath('data.totalOutstandingRupees', 570000)
            ->assertJsonPath('data.totalCcOutstandingRupees', 190000)
            ->assertJsonPath('data.totalMonthlyObligationsRupees', 26750)
            ->assertJsonPath('data.activeCreditCardsCount', 2)
            ->assertJsonPath('data.highestRateDebt', ['name' => 'HDFC Bank Millennia', 'ratePercent' => 42]);
    }

    public function test_viewing_the_summary_does_not_write_data(): void
    {
        $user = $this->createUser();
        $this->seedDebts($user);

        $this->getJson('/api/v1/dashboard/summary', $this->authHeaders($user))->assertOk();

        $this->assertSame(0, $user->debtSnapshots()->count());
    }

    public function test_snapshot_command_records_one_row_per_customer_per_month(): void
    {
        // 20:00 UTC on 31 Oct is already 1 Nov in India
        Carbon::setTestNow('2026-10-31 20:00:00');

        $user = $this->createUser();
        $this->seedDebts($user);
        $admin = $this->createAdmin();

        $this->artisan('navesta:capture-snapshots')->assertSuccessful();
        $this->artisan('navesta:capture-snapshots')->assertSuccessful();

        $this->assertSame(1, $user->debtSnapshots()->count());
        $this->assertSame(0, $admin->debtSnapshots()->count());

        $this->getJson('/api/v1/dashboard/history', $this->authHeaders($user))
            ->assertOk()
            ->assertJsonPath('data.0.month', '2026-11')
            ->assertJsonPath('data.0.totalDebtRupees', 570000)
            ->assertJsonPath('data.0.totalEmiRupees', 26750);
    }

    public function test_history_returns_latest_twelve_months_oldest_first(): void
    {
        $user = $this->createUser();
        foreach (range(1, 14) as $i) {
            $user->debtSnapshots()->create([
                'snapshot_month' => Carbon::create(2025, 1, 1)->addMonths($i - 1)->toDateString(),
                'cc_outstanding_paise' => $i * 100,
            ]);
        }

        $history = $this->getJson('/api/v1/dashboard/history', $this->authHeaders($user))->json('data');

        $this->assertCount(12, $history);
        $this->assertSame('2025-03', $history[0]['month']);
        $this->assertSame('2026-02', $history[11]['month']);
    }
}
