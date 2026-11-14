<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\LenderSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The website journeys' "Apply Now" (navesta_frontend/src/lib/applyOffer.ts):
 * fetch offers, save the customer's card or loan, then submit a balance-
 * transfer application. Request bodies here mirror what the website sends,
 * so a change to the API's validation that would break the button fails here.
 */
class WebsiteJourneyApplyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LenderSeeder::class);
    }

    private function headers(User $user, string $key): array
    {
        return $this->authHeaders($user) + ['Idempotency-Key' => $key];
    }

    public function test_credit_card_journey_applies_with_the_cheapest_offer_and_no_score_on_file(): void
    {
        $user = $this->createUser();

        $offers = $this->getJson('/api/v1/bt/offers?outstandingRupees=150000&tenureMonths=24', $this->authHeaders($user))
            ->assertOk()
            ->json('data');
        $this->assertNotEmpty($offers);
        $this->assertNull($offers[0]['meetsMinimumScore']);

        $cardId = $this->postJson('/api/v1/credit-cards', [
            'bankName' => 'HDFC Bank',
            'cardName' => 'HDFC Bank card',
            'creditLimitRupees' => 200000,
            'outstandingRupees' => 150000,
            'aprPercent' => 42,
            'minPaymentRupees' => 7500,
            'dueDay' => 5,
        ], $this->headers($user, 'card-run1-0-abc'))->assertCreated()->json('data.id');

        $this->postJson('/api/v1/bt/applications', [
            'lenderId' => $offers[0]['lenderId'],
            'requestedTenureMonths' => 24,
            'sourceCcId' => $cardId,
        ], $this->headers($user, 'bt-card-run1-0-lender-def'))->assertCreated();

        $this->assertSame(1, $user->btApplications()->count());
    }

    public function test_loan_journey_applies_and_a_retry_does_not_duplicate(): void
    {
        $user = $this->createUser();
        $lenderId = $this->getJson('/api/v1/bt/offers?outstandingRupees=80000&tenureMonths=24', $this->authHeaders($user))->json('data.0.lenderId');

        $loanId = $this->postJson('/api/v1/loans', [
            'lenderName' => 'Current lender',
            'loanType' => 'personal',
            'principalRupees' => 100000,
            'outstandingRupees' => 80000,
            'interestRatePercent' => 14,
            'emiRupees' => 3841.04,
            'tenureMonths' => 36,
            'tenureRemainingMonths' => 24,
        ], $this->headers($user, 'loan-run2-0-abc'))->assertCreated()->json('data.id');

        $application = ['lenderId' => $lenderId, 'requestedTenureMonths' => 24, 'sourceLoanId' => $loanId];
        $first = $this->postJson('/api/v1/bt/applications', $application, $this->headers($user, 'bt-loan-run2-x'))->assertCreated()->json('data.id');
        $retry = $this->postJson('/api/v1/bt/applications', $application, $this->headers($user, 'bt-loan-run2-x'))->json('data.id');

        $this->assertSame($first, $retry);
        $this->assertSame(1, $user->btApplications()->count());
    }

    public function test_business_loans_are_saved_as_other(): void
    {
        // The journey's "Business" loan type has no API equivalent; applyOffer.ts sends "other".
        $this->postJson('/api/v1/loans', [
            'lenderName' => 'Current lender',
            'loanType' => 'other',
            'principalRupees' => 500000,
            'outstandingRupees' => 300000,
            'interestRatePercent' => 16,
            'emiRupees' => 12000,
            'tenureMonths' => 48,
            'tenureRemainingMonths' => 30,
        ], $this->headers($this->createUser(), 'loan-run3-0-abc'))->assertCreated();
    }
}
