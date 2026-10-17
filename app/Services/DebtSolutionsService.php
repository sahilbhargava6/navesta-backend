<?php

namespace App\Services;

use App\Models\CreditCard;
use App\Models\Lender;
use App\Models\Loan;
use App\Models\User;
use App\Support\Financial;
use App\Support\Money;
use App\Support\PayoffSimulator;

class DebtSolutionsService
{
    public function __construct(
        private CreditScoreService $creditScores,
        private LenderMatcher $matcher,
        private LenderCatalog $lenders,
        private UserResultCache $cache,
    ) {}

    public function avalanche(User $user, float $extraMonthlyRupees = 0): array
    {
        // Highest rate first; ties go to the smaller balance
        $order = fn (array $a, array $b) => [$b['ratePercent'], $a['outstandingRupees']] <=> [$a['ratePercent'], $b['outstandingRupees']];

        return $this->cache->remember($user->id, "avalanche:{$extraMonthlyRupees}", fn () => $this->plan(
            $user,
            $extraMonthlyRupees,
            $order,
            'Debt Avalanche Method',
            'Pay minimum payments on all debts, and direct extra funds to the debt with the highest interest rate first for maximum interest savings.',
            '🔥 Target FIRST (Highest Interest Rate)',
        ));
    }

    public function snowball(User $user, float $extraMonthlyRupees = 0): array
    {
        // Smallest balance first; ties go to the higher rate
        $order = fn (array $a, array $b) => [$a['outstandingRupees'], $b['ratePercent']] <=> [$b['outstandingRupees'], $a['ratePercent']];

        return $this->cache->remember($user->id, "snowball:{$extraMonthlyRupees}", fn () => $this->plan(
            $user,
            $extraMonthlyRupees,
            $order,
            'Debt Snowball Method',
            'Pay minimum payments on all debts, and throw all extra funds at the smallest debt balance first for quick psychological wins.',
            '⚡ Target FIRST (Smallest Balance Quick Win)',
        ));
    }

    /**
     * Lenders ranked for this customer, with hard limits (score, amount) applied.
     */
    public function partnerEligibility(User $user): array
    {
        return $this->cache->remember($user->id, 'partner-eligibility', fn () => $this->computePartnerEligibility($user), dependsOnLenders: true);
    }

    private function computePartnerEligibility(User $user): array
    {
        $score = $this->creditScores->resolve($user);
        $totalDebtPaise = (int) $user->creditCards()->active()->sum('outstanding_paise')
            + (int) $user->loans()->active()->sum('outstanding_paise');

        $matches = $this->matcher
            ->rank($this->lenders->active(), $score->score, $totalDebtPaise, $user->employment_sector)
            ->map(fn (array $m) => [
                'lenderId' => $m['lender']->id,
                'lenderName' => $m['lender']->name,
                'type' => $m['lender']->type,
                'brandColor' => $m['lender']->brand_color,
                'minRatePercent' => Money::bpsToPercent($m['lender']->min_rate_bps),
                'maxRatePercent' => Money::bpsToPercent($m['lender']->max_rate_bps),
                'processingFeePercent' => Money::bpsToPercent($m['lender']->processing_fee_bps),
                'minCreditScore' => $m['lender']->min_credit_score,
                'disbursalDays' => "{$m['lender']->disbursal_days_min}-{$m['lender']->disbursal_days_max} days",
                'eligible' => $m['eligible'],
                'matchScore' => $m['matchScore'],
                // Pre-approval needs a known score, not a guess
                'preApproved' => $m['eligible'] && $score->isKnown() && $m['matchScore'] >= 80,
                'reasons' => $m['reasons'],
            ])
            ->all();

        return [
            'creditScore' => $score->score,
            'creditScoreSource' => $score->sourceLabel(),
            'totalDebtRupees' => Money::paiseToRupees($totalDebtPaise),
            'eligibleLendersCount' => count(array_filter($matches, fn (array $m) => $m['eligible'])),
            'matches' => $matches,
        ];
    }

    public function amortization(Loan $loan): array
    {
        $principal = Money::paiseToRupees($loan->principal_paise);
        $rate = Money::bpsToPercent($loan->interest_rate_bps);

        return [
            'loanId' => $loan->id,
            'lenderName' => $loan->lender_name,
            'loanType' => $loan->loan_type,
            'principalRupees' => $principal,
            'annualRatePercent' => $rate,
            'tenureMonths' => $loan->tenure_months,
            'tenureRemainingMonths' => $loan->tenure_remaining_months,
            ...Financial::amortizationSchedule($principal, $rate, $loan->tenure_months),
        ];
    }

    /**
     * Remaining cost of a loan versus moving it to the cheapest partner
     * lender that would accept it, net of that lender's processing fee.
     */
    public function loanAnalysis(Loan $loan): array
    {
        return $this->cache->remember($loan->user_id, "loan-analysis:{$loan->id}", fn () => $this->computeLoanAnalysis($loan), dependsOnLenders: true);
    }

    private function computeLoanAnalysis(Loan $loan): array
    {
        $outstanding = Money::paiseToRupees($loan->outstanding_paise);
        $emi = Money::paiseToRupees($loan->emi_paise);
        $remainingMonths = $loan->tenure_remaining_months;

        $totalRemainingPayment = $emi * $remainingMonths;
        $totalRemainingInterest = max(0, $totalRemainingPayment - $outstanding);

        $score = $this->creditScores->resolve($loan->user);
        // Catalogue is already sorted cheapest first
        $benchmark = $this->lenders->active()->first(fn (Lender $l) => $l->max_loan_amount_paise >= $loan->outstanding_paise
            && (! $score->isKnown() || $l->min_credit_score <= $score->score));

        $benchmarkRate = $benchmark
            ? Money::bpsToPercent($benchmark->min_rate_bps)
            : config('navesta.fallback_benchmark_rate_percent');
        $processingFee = $benchmark
            ? $outstanding * Money::bpsToPercent($benchmark->processing_fee_bps) / 100
            : 0;

        $newEmi = $remainingMonths > 0
            ? Financial::monthlyPayment($outstanding, $benchmarkRate, $remainingMonths)
            : $emi;
        $grossSavings = max(0, $totalRemainingPayment - $newEmi * $remainingMonths);

        return [
            'loanId' => $loan->id,
            'lenderName' => $loan->lender_name,
            'outstandingRupees' => $outstanding,
            'interestRatePercent' => Money::bpsToPercent($loan->interest_rate_bps),
            'tenureRemainingMonths' => $remainingMonths,
            'totalRemainingInterestRupees' => Money::round($totalRemainingInterest),
            'benchmarkRatePercent' => $benchmarkRate,
            'benchmarkLenderName' => $benchmark?->name,
            'newEmiRupees' => Money::round($newEmi),
            'monthlySavingsRupees' => Money::round(max(0, $emi - $newEmi)),
            'grossInterestSavingsRupees' => Money::round($grossSavings),
            'processingFeeRupees' => Money::round($processingFee),
            // Net of the processing fee, never below zero
            'totalBtSavingsEstimateRupees' => Money::round(max(0, $grossSavings - $processingFee)),
        ];
    }

    public function cibilTips(User $user): array
    {
        return $this->cache->remember($user->id, 'cibil-tips', fn () => $this->computeCibilTips($user));
    }

    private function computeCibilTips(User $user): array
    {
        $cards = $user->creditCards()->active()->get();
        $totalLimit = $cards->sum('credit_limit_paise');
        $totalOutstanding = $cards->sum('outstanding_paise');

        // Whole-percent utilisation
        $utilization = $totalLimit > 0 ? intdiv($totalOutstanding * 100, $totalLimit) : 0;
        $label = number_format($utilization, 1);

        $tips = [
            $utilization > 30
                ? [
                    'category' => 'Credit Utilization',
                    'priority' => 'high',
                    'title' => "High Credit Utilization ({$label}%)",
                    'advice' => 'Keep your total credit card utilization below 30% of your credit limit to boost your score by up to 40+ points.',
                ]
                : [
                    'category' => 'Credit Utilization',
                    'priority' => 'low',
                    'title' => "Healthy Utilization Ratio ({$label}%)",
                    'advice' => 'Great job keeping credit card balances low relative to total credit limits.',
                ],
            [
                'category' => 'Payment History',
                'priority' => 'high',
                'title' => 'Set Up Auto-Pay for Minimum Dues',
                'advice' => 'Payment history accounts for 35% of your score. Ensure zero missed or late payments.',
            ],
            [
                'category' => 'Credit Mix',
                'priority' => 'medium',
                'title' => 'Maintain a Balanced Debt Mix',
                'advice' => 'Having a healthy mix of secured (home/auto) and unsecured (credit cards) loans builds long-term score stability.',
            ],
        ];

        return [
            'utilizationRatioPercent' => $utilization,
            'tipsCount' => count($tips),
            'tips' => $tips,
        ];
    }

    /**
     * Shared avalanche / snowball plan: order the debts, simulate paying them
     * off with rollover, and compare with paying only the minimums.
     */
    private function plan(User $user, float $extraMonthly, callable $order, string $name, string $description, string $firstRecommendation): array
    {
        $debts = $this->activeDebts($user);
        usort($debts, $order);

        $maxMonths = (int) config('navesta.payoff.max_months');
        $simDebts = array_map(fn (array $d) => [
            'id' => $d['id'],
            'balance' => $d['outstandingRupees'],
            'ratePercent' => $d['ratePercent'],
            'minPayment' => $d['minMonthlyPaymentRupees'],
        ], $debts);

        $strategy = PayoffSimulator::run($simDebts, $extraMonthly, rollover: true, maxMonths: $maxMonths);
        $minimumsOnly = PayoffSimulator::run($simDebts, 0, rollover: false, maxMonths: $maxMonths);

        $totalMin = array_sum(array_column($debts, 'minMonthlyPaymentRupees'));
        $start = now(config('navesta.timezone'))->startOfMonth();
        $monthLabel = fn (?int $months) => $months === null ? null : $start->copy()->addMonths($months)->format('Y-m');

        return [
            'strategyName' => $name,
            'description' => $description,
            'totalDebtRupees' => Money::num(array_sum(array_column($debts, 'outstandingRupees'))),
            'totalMinMonthlyRupees' => Money::num($totalMin),
            'extraMonthlyRupees' => Money::num($extraMonthly),
            'monthlyBudgetRupees' => Money::num($totalMin + $extraMonthly),
            'monthsToDebtFree' => $strategy['months'],
            'debtFreeBy' => $monthLabel($strategy['months']),
            'totalInterestRupees' => Money::round($strategy['totalInterest']),
            'minimumPaymentsOnlyInterestRupees' => Money::round($minimumsOnly['totalInterest']),
            // Interest saved versus paying only each debt's minimum with no rollover
            'estimatedInterestSavingsRupees' => Money::round(max(0, $minimumsOnly['totalInterest'] - $strategy['totalInterest'])),
            'payoffSequence' => array_map(fn (array $debt, int $index) => [
                'rank' => $index + 1,
                'id' => $debt['id'],
                'name' => $debt['name'],
                'type' => $debt['type'],
                'outstandingRupees' => $debt['outstandingRupees'],
                'ratePercent' => $debt['ratePercent'],
                'minMonthlyPaymentRupees' => $debt['minMonthlyPaymentRupees'],
                'minPaymentEstimated' => $debt['minPaymentEstimated'],
                'paidOffInMonths' => $strategy['payoffMonth'][$debt['id']],
                'paidOffBy' => $monthLabel($strategy['payoffMonth'][$debt['id']]),
                'recommendation' => $index === 0 ? $firstRecommendation : 'Pay minimum monthly requirement',
            ], $debts, array_keys($debts)),
        ];
    }

    /**
     * Active cards and loans flattened into one list of debts. A card with
     * no minimum due on file gets an estimate (a share of its balance) so
     * the simulation can pay it down.
     *
     * @return list<array{id: string, name: string, type: string, outstandingRupees: int|float, ratePercent: int|float, minMonthlyPaymentRupees: int|float, minPaymentEstimated: bool}>
     */
    private function activeDebts(User $user): array
    {
        $percent = config('navesta.payoff.card_min_payment_percent');
        $floor = config('navesta.payoff.card_min_payment_floor_rupees');

        $cards = $user->creditCards()->active()->oldest()->get()->map(function (CreditCard $c) use ($percent, $floor) {
            $outstanding = Money::paiseToRupees($c->outstanding_paise);
            $estimated = ! $c->min_payment_paise;

            return [
                'id' => $c->id,
                'name' => "{$c->bank_name} {$c->card_name}",
                'type' => 'credit_card',
                'outstandingRupees' => $outstanding,
                'ratePercent' => Money::bpsToPercent($c->apr_bps),
                'minMonthlyPaymentRupees' => $estimated
                    ? Money::num(min($outstanding, max($floor, Money::round($outstanding * $percent / 100))))
                    : Money::paiseToRupees($c->min_payment_paise),
                'minPaymentEstimated' => $estimated,
            ];
        });

        $loans = $user->loans()->active()->oldest()->get()->map(fn (Loan $l) => [
            'id' => $l->id,
            'name' => "{$l->lender_name} ({$l->loan_type->value})",
            'type' => 'loan',
            'outstandingRupees' => Money::paiseToRupees($l->outstanding_paise),
            'ratePercent' => Money::bpsToPercent($l->interest_rate_bps),
            'minMonthlyPaymentRupees' => Money::paiseToRupees($l->emi_paise),
            'minPaymentEstimated' => false,
        ]);

        return [...$cards->all(), ...$loans->all()];
    }
}
