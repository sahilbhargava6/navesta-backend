<?php

namespace Tests\Feature;

use App\Enums\CreditScoreSource;
use App\Models\User;
use Database\Seeders\LenderSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SolutionsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->createUser();
        $this->headers = $this->authHeaders($this->user);
        $this->user->creditCards()->create(['bank_name' => 'HDFC Bank', 'card_name' => 'Millennia', 'credit_limit_paise' => 25000000, 'outstanding_paise' => 12500000, 'apr_bps' => 4200, 'min_payment_paise' => 625000]);
        // No minimum on file: the plan estimates 5% of the balance
        $this->user->creditCards()->create(['bank_name' => 'SBI Card', 'card_name' => 'SimplyCLICK', 'credit_limit_paise' => 15000000, 'outstanding_paise' => 6500000, 'apr_bps' => 3900]);
        $this->user->loans()->create(['lender_name' => 'Bajaj Finserv', 'loan_type' => 'personal', 'principal_paise' => 50000000, 'outstanding_paise' => 38000000, 'interest_rate_bps' => 1450, 'emi_paise' => 1725000, 'tenure_months' => 36, 'tenure_remaining_months' => 24]);
    }

    public function test_avalanche_orders_by_highest_rate_and_simulates_payoff(): void
    {
        $data = $this->getJson('/api/v1/solutions/avalanche', $this->headers)->assertOk()->json('data');

        $this->assertSame(['HDFC Bank Millennia', 'SBI Card SimplyCLICK', 'Bajaj Finserv (personal)'], array_column($data['payoffSequence'], 'name'));
        $this->assertSame(570000, $data['totalDebtRupees']);
        $this->assertSame(3250, $data['payoffSequence'][1]['minMonthlyPaymentRupees']);
        $this->assertTrue($data['payoffSequence'][1]['minPaymentEstimated']);
        $this->assertSame(26750, $data['monthlyBudgetRupees']);

        // Rolling freed-up payments forward costs less interest than minimums only
        $this->assertIsInt($data['monthsToDebtFree']);
        $this->assertLessThan($data['minimumPaymentsOnlyInterestRupees'], $data['totalInterestRupees']);
        $this->assertEqualsWithDelta(
            $data['minimumPaymentsOnlyInterestRupees'] - $data['totalInterestRupees'],
            $data['estimatedInterestSavingsRupees'],
            1,
            'savings = minimums-only interest minus plan interest (±1 rounding)',
        );
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}$/', $data['debtFreeBy']);

        // The first target is cleared first
        $months = array_column($data['payoffSequence'], 'paidOffInMonths');
        $this->assertLessThanOrEqual($months[1], $months[0]);
    }

    public function test_extra_monthly_payment_shortens_the_plan(): void
    {
        $base = $this->getJson('/api/v1/solutions/avalanche', $this->headers)->json('data');
        $extra = $this->getJson('/api/v1/solutions/avalanche?extraMonthlyRupees=10000', $this->headers)->json('data');

        $this->assertSame(10000, $extra['extraMonthlyRupees']);
        $this->assertLessThan($base['monthsToDebtFree'], $extra['monthsToDebtFree']);
        $this->assertLessThan($base['totalInterestRupees'], $extra['totalInterestRupees']);
    }

    public function test_extra_monthly_payment_is_validated(): void
    {
        $this->getJson('/api/v1/solutions/avalanche?extraMonthlyRupees=-5', $this->headers)->assertStatus(400);
        $this->getJson('/api/v1/solutions/avalanche?extraMonthlyRupees=10.555', $this->headers)->assertStatus(400);
    }

    public function test_snowball_orders_by_smallest_balance(): void
    {
        $data = $this->getJson('/api/v1/solutions/snowball', $this->headers)->assertOk()->json('data');

        $this->assertSame(['SBI Card SimplyCLICK', 'HDFC Bank Millennia', 'Bajaj Finserv (personal)'], array_column($data['payoffSequence'], 'name'));
        $this->assertArrayHasKey('estimatedInterestSavingsRupees', $data);
    }

    public function test_partner_eligibility_scores_each_lender(): void
    {
        $this->seed(LenderSeeder::class);
        $this->user->creditScores()->create(['score' => 760, 'source' => CreditScoreSource::CibilApi, 'fetched_at' => now()]);

        $data = $this->getJson('/api/v1/solutions/eligibility', $this->headers)->assertOk()->json('data');

        $this->assertSame(760, $data['creditScore']);
        $this->assertSame('cibil_api', $data['creditScoreSource']);
        $this->assertSame(8, $data['eligibleLendersCount']);
        // SBI: score 30+12, amount 20, rate 19 (2nd cheapest), salaried 10
        $this->assertSame('State Bank of India', $data['matches'][0]['lenderName']);
        $this->assertSame(91, $data['matches'][0]['matchScore']);
        $this->assertTrue($data['matches'][0]['preApproved']);
        $this->assertSame('Credit score 760 meets the 700 minimum', $data['matches'][0]['reasons'][0]);

        $scores = array_column($data['matches'], 'matchScore');
        $sorted = $scores;
        rsort($sorted);
        $this->assertSame($sorted, $scores);
    }

    public function test_partner_eligibility_excludes_lenders_above_the_customers_score(): void
    {
        $this->seed(LenderSeeder::class);
        $this->user->creditScores()->create(['score' => 690, 'source' => CreditScoreSource::CibilApi, 'fetched_at' => now()]);

        $data = $this->getJson('/api/v1/solutions/eligibility', $this->headers)->json('data');

        $this->assertSame(4, $data['eligibleLendersCount']);
        $ineligible = array_values(array_filter($data['matches'], fn ($m) => ! $m['eligible']));
        $this->assertCount(4, $ineligible);
        $this->assertSame(0, $ineligible[0]['matchScore']);
        $this->assertFalse($ineligible[0]['preApproved']);
    }

    public function test_without_a_score_nobody_is_pre_approved(): void
    {
        $this->seed(LenderSeeder::class);

        $data = $this->getJson('/api/v1/solutions/eligibility', $this->headers)->json('data');

        $this->assertNull($data['creditScore']);
        $this->assertSame([false], array_values(array_unique(array_column($data['matches'], 'preApproved'))));
    }

    public function test_loan_analysis_uses_the_cheapest_lender_that_would_accept_it(): void
    {
        $this->seed(LenderSeeder::class);
        $loanId = $this->user->loans()->first()->id;

        $data = $this->getJson("/api/v1/loans/{$loanId}/analysis", $this->headers)->assertOk()->json('data');

        // No score on file: every lender is a candidate, HDFC is cheapest
        $this->assertSame('HDFC Bank', $data['benchmarkLenderName']);
        $this->assertSame(8.5, $data['benchmarkRatePercent']);
        $this->assertSame(3800, $data['processingFeeRupees']); // 1% of ₹3.8 lakh
        $this->assertSame(max(0, $data['grossInterestSavingsRupees'] - 3800), $data['totalBtSavingsEstimateRupees']);
    }

    public function test_cibil_tips_flag_high_utilization(): void
    {
        // ₹1.9L outstanding on ₹4L limit = 47%
        $this->getJson('/api/v1/solutions/cibil-tips', $this->headers)
            ->assertOk()
            ->assertJsonPath('data.utilizationRatioPercent', 47)
            ->assertJsonPath('data.tips.0.title', 'High Credit Utilization (47.0%)')
            ->assertJsonPath('data.tipsCount', 3);
    }

    public function test_reminder_payload_keeps_the_frontend_shape(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/notifications/reminder', [
            'channel' => 'whatsapp',
            'accountName' => 'HDFC Bank Millennia',
            'amountRupees' => 125000,
            'dueDate' => '15th November',
        ], $this->headers)
            ->assertStatus(202)
            ->assertJsonPath('payload.recipient', '+919800000000')
            ->assertJsonPath('payload.dispatchStatus', 'QUEUED')
            ->assertJsonPath('payload.content', fn (string $content) => str_contains($content, 'Hi Asha!') && str_contains($content, '₹1,25,000'));
    }
}
