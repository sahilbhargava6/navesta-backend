<?php

namespace Tests\Feature;

use App\Enums\BtStatus;
use App\Enums\CreditScoreSource;
use App\Models\Lender;
use App\Models\User;
use Database\Seeders\LenderSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BalanceTransferTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LenderSeeder::class);
    }

    private function cardFor(User $user, int $outstandingPaise = 12500000)
    {
        return $user->creditCards()->create(['bank_name' => 'HDFC Bank', 'card_name' => 'Millennia', 'credit_limit_paise' => 25000000, 'outstanding_paise' => $outstandingPaise, 'apr_bps' => 4200]);
    }

    public function test_lenders_are_public_and_sorted_by_rate(): void
    {
        $this->getJson('/api/v1/lenders?type=nbfc')
            ->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('data.0.name', 'Bajaj Finserv')
            ->assertJsonPath('data.0.minRatePercent', 10.5)
            ->assertJsonPath('data.0.minCreditScore', 685);
    }

    public function test_eligibility_without_a_score_on_file_is_unscored(): void
    {
        $this->postJson('/api/v1/bt/eligibility', ['outstandingRupees' => 100000, 'currentRatePercent' => 42], $this->authHeaders($this->createUser()))
            ->assertOk()
            ->assertJsonPath('data.tier', 'unscored')
            ->assertJsonPath('data.isEligible', false)
            ->assertJsonPath('data.crifScoreUsed', null)
            ->assertJsonPath('data.creditScoreSource', 'none');
    }

    public function test_eligibility_uses_the_bureau_score_over_the_one_sent(): void
    {
        $user = $this->createUser();
        $user->creditScores()->create(['score' => 640, 'source' => CreditScoreSource::CibilApi, 'fetched_at' => now()]);

        // Claiming 800 in the request does not override the bureau's 640
        $this->postJson('/api/v1/bt/eligibility', ['outstandingRupees' => 100000, 'currentRatePercent' => 42, 'crifScore' => 800], $this->authHeaders($user))
            ->assertOk()
            ->assertJsonPath('data.tier', 'subprime')
            ->assertJsonPath('data.crifScoreUsed', 640)
            ->assertJsonPath('data.creditScoreSource', 'cibil_api');

        $this->assertSame(1, $user->creditScores()->count());
    }

    public function test_self_reported_score_is_recorded_when_no_bureau_score_exists(): void
    {
        $user = $this->createUser();

        $this->postJson('/api/v1/bt/eligibility', ['outstandingRupees' => 100000, 'currentRatePercent' => 42, 'crifScore' => 760], $this->authHeaders($user))
            ->assertJsonPath('data.tier', 'prime')
            ->assertJsonPath('data.creditScoreSource', 'user_input')
            ->assertJsonPath('data.lowestAvailableRatePercent', 8.5)
            ->assertJsonPath('data.maxPotentialRateReductionPercent', 33.5);

        $this->assertDatabaseHas('credit_scores', ['user_id' => $user->id, 'score' => 760, 'source' => 'user_input']);
    }

    public function test_lowest_rate_respects_lender_minimum_scores(): void
    {
        $user = $this->createUser();
        $user->creditScores()->create(['score' => 690, 'source' => CreditScoreSource::CrifApi, 'fetched_at' => now()]);

        // 690 is below every bank's minimum; the cheapest lender left is Bajaj at 10.5%
        $this->postJson('/api/v1/bt/eligibility', ['outstandingRupees' => 100000, 'currentRatePercent' => 42], $this->authHeaders($user))
            ->assertJsonPath('data.tier', 'standard')
            ->assertJsonPath('data.lowestAvailableRatePercent', 10.5);
    }

    public function test_offers_exclude_lenders_below_the_amount_and_flag_score_fit(): void
    {
        $user = $this->createUser();
        $user->creditScores()->create(['score' => 710, 'source' => CreditScoreSource::CibilApi, 'fetched_at' => now()]);

        $offers = $this->getJson('/api/v1/bt/offers?outstandingRupees=4500000&tenureMonths=36', $this->authHeaders($user))
            ->assertOk()
            ->json('data');

        $this->assertSame(['HDFC Bank', 'ICICI Bank', 'Axis Bank'], array_column($offers, 'name'));
        $this->assertSame(45000, $offers[0]['processingFeeRupees']);
        $this->assertSame([false, false, true], array_column($offers, 'meetsMinimumScore'));
        $this->assertGreaterThan(0, $offers[0]['totalInterestRupees']);
    }

    public function test_application_requires_a_source_debt(): void
    {
        $this->postJson('/api/v1/bt/applications', [
            'lenderId' => Lender::first()->id,
            'requestedTenureMonths' => 36,
        ], $this->authHeaders($this->createUser()))
            ->assertStatus(400)
            ->assertJsonPath('error', 'Either sourceLoanId or sourceCcId must be provided');
    }

    public function test_application_from_a_credit_card(): void
    {
        $user = $this->createUser();
        $headers = $this->authHeaders($user);
        $card = $this->cardFor($user);
        $lender = Lender::where('name', 'HDFC Bank')->first();

        $this->postJson('/api/v1/bt/applications', [
            'lenderId' => $lender->id,
            'sourceCcId' => $card->id,
            'requestedTenureMonths' => 24,
        ], $headers)
            ->assertCreated()
            ->assertJsonPath('data.lenderName', 'HDFC Bank')
            ->assertJsonPath('data.outstandingRupees', 125000)
            ->assertJsonPath('data.currentRatePercent', 42)
            ->assertJsonPath('data.newRatePercent', 8.5)
            ->assertJsonPath('data.status', 'submitted');

        $this->getJson('/api/v1/bt/applications', $headers)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_application_is_refused_below_the_lenders_minimum_score(): void
    {
        $user = $this->createUser();
        $user->creditScores()->create(['score' => 700, 'source' => CreditScoreSource::CibilApi, 'fetched_at' => now()]);
        $card = $this->cardFor($user);

        $this->postJson('/api/v1/bt/applications', [
            'lenderId' => Lender::where('name', 'HDFC Bank')->first()->id,
            'sourceCcId' => $card->id,
            'requestedTenureMonths' => 24,
        ], $this->authHeaders($user))
            ->assertStatus(400)
            ->assertJsonPath('error', "Your credit score (700) is below HDFC Bank's minimum of 725");
    }

    public function test_duplicate_open_application_is_refused(): void
    {
        $user = $this->createUser();
        $headers = $this->authHeaders($user);
        $payload = [
            'lenderId' => Lender::where('name', 'Axis Bank')->first()->id,
            'sourceCcId' => $this->cardFor($user)->id,
            'requestedTenureMonths' => 24,
        ];

        $this->postJson('/api/v1/bt/applications', $payload, $headers)->assertCreated();
        $this->postJson('/api/v1/bt/applications', $payload, $headers)
            ->assertStatus(400)
            ->assertJsonPath('error', 'You already have an open application with Axis Bank for this account');
    }

    public function test_balance_above_the_lenders_limit_is_refused(): void
    {
        $user = $this->createUser();
        $card = $this->cardFor($user, outstandingPaise: 300000000); // ₹30 lakh; Muthoot lends up to ₹20 lakh

        $this->postJson('/api/v1/bt/applications', [
            'lenderId' => Lender::where('name', 'Muthoot Finance')->first()->id,
            'sourceCcId' => $card->id,
            'requestedTenureMonths' => 24,
        ], $this->authHeaders($user))
            ->assertStatus(400)
            ->assertJsonPath('error', 'Muthoot Finance cannot take over a balance this large');
    }

    public function test_status_workflow_only_moves_forward(): void
    {
        $this->assertTrue(BtStatus::Submitted->canTransitionTo(BtStatus::Approved));
        $this->assertTrue(BtStatus::Approved->canTransitionTo(BtStatus::Disbursed));
        $this->assertFalse(BtStatus::Submitted->canTransitionTo(BtStatus::Disbursed));
        $this->assertFalse(BtStatus::Rejected->canTransitionTo(BtStatus::Approved));
        $this->assertFalse(BtStatus::Disbursed->canTransitionTo(BtStatus::Rejected));
    }
}
