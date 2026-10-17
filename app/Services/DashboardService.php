<?php

namespace App\Services;

use App\Models\DebtSnapshot;
use App\Models\User;
use App\Support\Money;

class DashboardService
{
    public function __construct(private UserResultCache $cache) {}

    public function summary(User $user): array
    {
        return $this->cache->remember($user->id, 'dashboard-summary', fn () => $this->computeSummary($user));
    }

    private function computeSummary(User $user): array
    {
        $cards = $user->creditCards()->active()->get();
        $loans = $user->loans()->active()->get();
        $totals = $this->totals($user);

        $highestRateName = 'None';
        $highestRateBps = 0;

        foreach ($cards as $card) {
            if ($card->apr_bps > $highestRateBps) {
                $highestRateBps = $card->apr_bps;
                $highestRateName = "{$card->bank_name} {$card->card_name}";
            }
        }

        foreach ($loans as $loan) {
            if ($loan->interest_rate_bps > $highestRateBps) {
                $highestRateBps = $loan->interest_rate_bps;
                $highestRateName = "{$loan->lender_name} ({$loan->loan_type->value})";
            }
        }

        return [
            'totalOutstandingRupees' => Money::paiseToRupees($totals['cc'] + $totals['loan']),
            'totalCcOutstandingRupees' => Money::paiseToRupees($totals['cc']),
            'totalLoanOutstandingRupees' => Money::paiseToRupees($totals['loan']),
            'totalMonthlyObligationsRupees' => Money::paiseToRupees($totals['monthly']),
            'activeCreditCardsCount' => $cards->count(),
            'activeLoansCount' => $loans->count(),
            'highestRateDebt' => [
                'name' => $highestRateName,
                'ratePercent' => Money::bpsToPercent($highestRateBps),
            ],
        ];
    }

    /**
     * Record (or refresh) the current month's debt totals for the history
     * chart. Run for every user by the navesta:capture-snapshots command.
     */
    public function captureMonthlySnapshot(User $user): void
    {
        $totals = $this->totals($user);

        DebtSnapshot::upsert(
            [[
                'user_id' => $user->id,
                'snapshot_month' => now(config('navesta.timezone'))->startOfMonth()->toDateString(),
                'cc_outstanding_paise' => $totals['cc'],
                'loan_outstanding_paise' => $totals['loan'],
                'total_emi_paise' => $totals['monthly'],
            ]],
            uniqueBy: ['user_id', 'snapshot_month'],
            update: ['cc_outstanding_paise', 'loan_outstanding_paise', 'total_emi_paise'],
        );
    }

    /**
     * The most recent 12 months, oldest first.
     */
    public function history(User $user): array
    {
        return $user->debtSnapshots()
            ->orderByDesc('snapshot_month')
            ->limit(12)
            ->get()
            ->reverse()
            ->map(fn (DebtSnapshot $s) => [
                'month' => $s->snapshot_month->format('Y-m'),
                'ccOutstandingRupees' => Money::paiseToRupees($s->cc_outstanding_paise),
                'loanOutstandingRupees' => Money::paiseToRupees($s->loan_outstanding_paise),
                'totalDebtRupees' => Money::paiseToRupees($s->cc_outstanding_paise + $s->loan_outstanding_paise),
                'totalEmiRupees' => Money::paiseToRupees($s->total_emi_paise),
            ])
            ->values()
            ->all();
    }

    /**
     * Outstanding balances and monthly obligations (card minimums + EMIs), in paise.
     *
     * @return array{cc: int, loan: int, monthly: int}
     */
    private function totals(User $user): array
    {
        $cards = $user->creditCards()->active();
        $loans = $user->loans()->active();

        return [
            'cc' => (int) (clone $cards)->sum('outstanding_paise'),
            'loan' => (int) (clone $loans)->sum('outstanding_paise'),
            'monthly' => (int) (clone $cards)->sum('min_payment_paise') + (int) (clone $loans)->sum('emi_paise'),
        ];
    }
}
