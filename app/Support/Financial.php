<?php

namespace App\Support;

/**
 * EMI and amortization maths (reducing-balance method).
 */
final class Financial
{
    /**
     * Unrounded monthly payment: P x R x (1+R)^N / ((1+R)^N - 1).
     */
    public static function monthlyPayment(float|int $principalRupees, float|int $annualRatePercent, int $tenureMonths): float
    {
        $monthlyRate = $annualRatePercent / 12 / 100;
        $factor = (1 + $monthlyRate) ** $tenureMonths;

        return $principalRupees * $monthlyRate * $factor / ($factor - 1);
    }

    /**
     * Monthly EMI rounded to the nearest rupee.
     */
    public static function emi(float|int $principalRupees, float|int $annualRatePercent, int $tenureMonths): int
    {
        if ($principalRupees <= 0 || $tenureMonths <= 0) {
            return 0;
        }

        if ($annualRatePercent <= 0) {
            return Money::round($principalRupees / $tenureMonths);
        }

        return Money::round(self::monthlyPayment($principalRupees, $annualRatePercent, $tenureMonths));
    }

    /**
     * Month-by-month amortization schedule.
     *
     * @return array{emiRupees: int, totalInterestRupees: int|float, totalPaymentRupees: int|float, schedule: list<array<string, int|float>>}
     */
    public static function amortizationSchedule(float|int $principalRupees, float|int $annualRatePercent, int $tenureMonths): array
    {
        $emi = self::emi($principalRupees, $annualRatePercent, $tenureMonths);
        $monthlyRate = $annualRatePercent / 12 / 100;

        $balance = $principalRupees;
        $totalInterest = 0;
        $schedule = [];

        for ($month = 1; $month <= $tenureMonths; $month++) {
            $interestComponent = Money::round($balance * $monthlyRate);
            $principalComponent = $emi - $interestComponent;

            // Final month (or overshoot) clears whatever is left after rounding
            if ($month === $tenureMonths || $balance - $principalComponent < 0) {
                $principalComponent = $balance;
            }

            $closingBalance = max(0, $balance - $principalComponent);
            $totalInterest += $interestComponent;

            $schedule[] = [
                'month' => $month,
                'openingBalanceRupees' => Money::num($balance),
                'emiRupees' => Money::num($interestComponent + $principalComponent),
                'interestComponentRupees' => $interestComponent,
                'principalComponentRupees' => Money::num($principalComponent),
                'closingBalanceRupees' => Money::num($closingBalance),
            ];

            $balance = $closingBalance;
            if ($balance <= 0) {
                break;
            }
        }

        return [
            'emiRupees' => $emi,
            'totalInterestRupees' => $totalInterest,
            'totalPaymentRupees' => Money::num($principalRupees + $totalInterest),
            'schedule' => $schedule,
        ];
    }
}
