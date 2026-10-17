<?php

namespace App\Support;

/**
 * Month-by-month debt payoff simulation.
 *
 * Each month every open debt accrues interest (annual rate / 12) and gets its
 * minimum payment. With rollover (avalanche / snowball), the customer keeps
 * paying the same total every month: the minimums of every debt plus any
 * extra amount, so money freed by a cleared debt goes to the next target in
 * the strategy's order. Without rollover each debt only ever gets its own
 * minimum (the "minimum payments only" baseline).
 */
final class PayoffSimulator
{
    private const PAID = 0.005; // below half a paisa counts as cleared

    /**
     * @param  list<array{id: string, balance: float|int, ratePercent: float|int, minPayment: float|int}>  $debts  in target order
     * @return array{months: ?int, totalInterest: float, payoffMonth: array<string, ?int>}
     */
    public static function run(array $debts, float $extraMonthly = 0, bool $rollover = true, int $maxMonths = 600): array
    {
        $balance = array_column($debts, 'balance', 'id');
        $payoffMonth = array_fill_keys(array_keys($balance), null);
        $budget = array_sum(array_column($debts, 'minPayment')) + $extraMonthly;
        $totalInterest = 0.0;

        foreach ($balance as $id => $b) {
            if ($b <= self::PAID) {
                $payoffMonth[$id] = 0;
            }
        }

        for ($month = 1; $month <= $maxMonths; $month++) {
            if (! self::anyOpen($balance)) {
                return ['months' => $month - 1, 'totalInterest' => $totalInterest, 'payoffMonth' => $payoffMonth];
            }

            $available = $budget;

            foreach ($debts as $debt) {
                $id = $debt['id'];
                if ($balance[$id] <= self::PAID) {
                    continue;
                }

                $interest = $balance[$id] * $debt['ratePercent'] / 1200;
                $totalInterest += $interest;
                $balance[$id] += $interest;

                $payment = min($balance[$id], $debt['minPayment']);
                $balance[$id] -= $payment;
                $available -= $payment;
            }

            if ($rollover) {
                foreach ($debts as $debt) {
                    $id = $debt['id'];
                    if ($available <= self::PAID) {
                        break;
                    }
                    if ($balance[$id] > self::PAID) {
                        $payment = min($balance[$id], $available);
                        $balance[$id] -= $payment;
                        $available -= $payment;
                    }
                }
            }

            foreach ($balance as $id => $b) {
                if ($b <= self::PAID && $payoffMonth[$id] === null) {
                    $payoffMonth[$id] = $month;
                }
            }
        }

        return [
            'months' => self::anyOpen($balance) ? null : $maxMonths,
            'totalInterest' => $totalInterest,
            'payoffMonth' => $payoffMonth,
        ];
    }

    private static function anyOpen(array $balance): bool
    {
        foreach ($balance as $b) {
            if ($b > self::PAID) {
                return true;
            }
        }

        return false;
    }
}
