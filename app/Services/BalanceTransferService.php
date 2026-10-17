<?php

namespace App\Services;

use App\Enums\BtStatus;
use App\Models\BtApplication;
use App\Models\Lender;
use App\Models\Loan;
use App\Models\User;
use App\Support\Financial;
use App\Support\Money;

class BalanceTransferService
{
    public function __construct(
        private CreditScoreService $creditScores,
        private LenderCatalog $lenders,
    ) {}

    /**
     * Tier by the score on file. A score sent in the request is only used
     * (and recorded as self-reported) when no bureau score exists.
     */
    public function eligibility(User $user, array $input): array
    {
        $score = $this->creditScores->resolve($user, $input['crifScore'] ?? null);
        $currentRate = Money::num((float) $input['currentRatePercent']);

        [$isEligible, $tier, $message] = match (true) {
            ! $score->isKnown() => [false, 'unscored', 'Add your credit score to check Balance Transfer eligibility'],
            $score->score < 650 => [false, 'subprime', 'Credit score is below minimum threshold for Balance Transfer (650+ required)'],
            $score->score < 720 => [true, 'standard', 'Eligible for standard Balance Transfer rates with select lenders'],
            default => [true, 'prime', 'You qualify for our lowest Balance Transfer rates!'],
        };

        $amountPaise = Money::rupeesToPaise($input['outstandingRupees']);
        $lowest = $this->lenders->active()
            ->filter(fn (Lender $l) => $l->max_loan_amount_paise >= $amountPaise && (! $score->isKnown() || $l->min_credit_score <= $score->score))
            ->min('min_rate_bps');
        $lowestRate = $lowest !== null
            ? Money::bpsToPercent((int) $lowest)
            : config('navesta.fallback_benchmark_rate_percent');

        return [
            'isEligible' => $isEligible,
            'tier' => $tier,
            'crifScoreUsed' => $score->score,
            'creditScoreSource' => $score->sourceLabel(),
            'currentRatePercent' => $currentRate,
            'lowestAvailableRatePercent' => $lowestRate,
            'maxPotentialRateReductionPercent' => Money::num(round(max(0, $currentRate - $lowestRate), 2)),
            'message' => $message,
        ];
    }

    /**
     * Active lenders able to take over the balance, cheapest first, with
     * whether the customer's score on file meets each lender's minimum.
     */
    public function rankedOffers(User $user, float $outstandingRupees, int $tenureMonths = 36, ?string $type = null): array
    {
        $score = $this->creditScores->resolve($user);

        $amountPaise = Money::rupeesToPaise($outstandingRupees);

        return $this->lenders->active()
            ->filter(fn (Lender $l) => $l->max_loan_amount_paise >= $amountPaise && ($type === null || $l->type->value === $type))
            ->values()
            ->map(function (Lender $lender) use ($outstandingRupees, $tenureMonths, $score) {
                $rate = Money::bpsToPercent($lender->min_rate_bps);
                $emi = $rate > 0 && $tenureMonths > 0
                    ? Financial::monthlyPayment($outstandingRupees, $rate, $tenureMonths)
                    : 0;
                $processingFee = $outstandingRupees * Money::bpsToPercent($lender->processing_fee_bps) / 100;

                return [
                    'lenderId' => $lender->id,
                    'name' => $lender->name,
                    'type' => $lender->type,
                    'initials' => $lender->initials,
                    'brandColor' => $lender->brand_color,
                    'offeredRatePercent' => $rate,
                    'estimatedEmiRupees' => Money::round($emi),
                    'totalInterestRupees' => Money::round(max(0, $emi * $tenureMonths - $outstandingRupees)),
                    'processingFeeRupees' => Money::round($processingFee),
                    'minCreditScore' => $lender->min_credit_score,
                    'meetsMinimumScore' => $score->isKnown() ? $score->score >= $lender->min_credit_score : null,
                    'disbursalDaysMin' => $lender->disbursal_days_min,
                    'disbursalDaysMax' => $lender->disbursal_days_max,
                ];
            })
            ->all();
    }

    /**
     * Submit an application to move one of the user's loans or cards to a lender.
     */
    public function apply(User $user, array $input): BtApplication
    {
        if (! empty($input['sourceLoanId'])) {
            $source = $user->loans()->active()->find($input['sourceLoanId']) ?? abort(400, 'Source loan not found');
            $currentRateBps = $source->interest_rate_bps;
        } else {
            $source = $user->creditCards()->active()->find($input['sourceCcId']) ?? abort(400, 'Source credit card not found');
            $currentRateBps = $source->apr_bps;
        }

        $lender = $this->lenders->active()->firstWhere('id', $input['lenderId']) ?? abort(400, 'Lender not found');

        if ($source->outstanding_paise <= 0) {
            abort(400, 'This account has no outstanding balance to transfer');
        }

        if ($source->outstanding_paise > $lender->max_loan_amount_paise) {
            abort(400, "{$lender->name} cannot take over a balance this large");
        }

        $score = $this->creditScores->resolve($user, $input['crifScore'] ?? null);
        if ($score->isKnown() && $score->score < $lender->min_credit_score) {
            abort(400, "Your credit score ({$score->score}) is below {$lender->name}'s minimum of {$lender->min_credit_score}");
        }

        $sourceColumn = $source instanceof Loan ? 'source_loan_id' : 'source_cc_id';
        $alreadyOpen = $user->btApplications()
            ->where('lender_id', $lender->id)
            ->where($sourceColumn, $source->id)
            ->whereIn('status', BtStatus::open())
            ->exists();
        if ($alreadyOpen) {
            abort(400, "You already have an open application with {$lender->name} for this account");
        }

        $application = $user->btApplications()->create([
            'lender_id' => $lender->id,
            'source_loan_id' => $source instanceof Loan ? $source->id : null,
            'source_cc_id' => $source instanceof Loan ? null : $source->id,
            'outstanding_paise' => $source->outstanding_paise,
            'current_rate_bps' => $currentRateBps,
            'new_rate_bps' => $lender->min_rate_bps,
            'requested_tenure_months' => $input['requestedTenureMonths'],
            'crif_score' => $score->score,
            'status' => BtStatus::Submitted,
        ]);

        return $application->setRelation('lender', $lender);
    }

    /**
     * Admin review step; only forward moves in the workflow are allowed.
     */
    public function transition(BtApplication $application, BtStatus $next): BtApplication
    {
        if (! $application->status->canTransitionTo($next)) {
            throw new \DomainException("An application that is {$application->status->getLabel()} cannot be marked {$next->getLabel()}");
        }

        $application->update(['status' => $next]);

        return $application;
    }
}
