<?php

namespace App\Services;

use App\Enums\EmploymentSector;
use App\Models\Lender;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * Scores how well a lender fits a customer (0-100) and whether the customer
 * clears the lender's hard limits (credit score, loan amount).
 *
 * Weights: credit score 50, amount fit 20, rate competitiveness 20,
 * employment 10.
 */
class LenderMatcher
{
    /**
     * @param  Collection<int, Lender>  $lenders  the lenders being compared (sets the rate range)
     * @return Collection<int, array{lender: Lender, eligible: bool, matchScore: int, reasons: list<string>}>
     */
    public function rank(Collection $lenders, ?int $creditScore, int $amountPaise, ?EmploymentSector $sector): Collection
    {
        $cheapest = (int) $lenders->min('min_rate_bps');
        $priciest = (int) $lenders->max('min_rate_bps');

        return $lenders
            ->map(fn (Lender $lender) => $this->evaluate($lender, $creditScore, $amountPaise, $sector, $cheapest, $priciest))
            ->sortByDesc(fn (array $m) => [$m['eligible'], $m['matchScore'], -$m['lender']->min_rate_bps])
            ->values();
    }

    /**
     * @return array{lender: Lender, eligible: bool, matchScore: int, reasons: list<string>}
     */
    public function evaluate(Lender $lender, ?int $creditScore, int $amountPaise, ?EmploymentSector $sector, int $cheapestBps, int $priciestBps): array
    {
        $eligible = true;
        $reasons = [];

        // Credit score: 50 points. Unknown scores get half credit; the lender checks the bureau.
        if ($creditScore === null) {
            $scorePoints = 25;
            $reasons[] = "Needs a credit score check (minimum {$lender->min_credit_score})";
        } elseif ($creditScore < $lender->min_credit_score) {
            $scorePoints = 0;
            $eligible = false;
            $reasons[] = "Credit score {$creditScore} is below the {$lender->min_credit_score} minimum";
        } else {
            $scorePoints = 30 + min(20, intdiv($creditScore - $lender->min_credit_score, 5));
            $reasons[] = "Credit score {$creditScore} meets the {$lender->min_credit_score} minimum";
        }

        // Amount: 20 points if the lender can take over the whole balance.
        if ($amountPaise <= $lender->max_loan_amount_paise) {
            $amountPoints = 20;
        } else {
            $amountPoints = 0;
            $eligible = false;
            $reasons[] = 'Balance exceeds the ₹'.Money::formatInr(Money::paiseToRupees($lender->max_loan_amount_paise)).' lending limit';
        }

        // Rate: 20 points for the cheapest lender in the comparison, scaling down to 0 for the priciest.
        $ratePoints = $priciestBps > $cheapestBps
            ? (int) round(20 * ($priciestBps - $lender->min_rate_bps) / ($priciestBps - $cheapestBps))
            : 20;

        // Employment: salaried applicants are the easiest to underwrite.
        $employmentPoints = match ($sector) {
            EmploymentSector::Govt, EmploymentSector::Pvt => 10,
            default => 5,
        };

        return [
            'lender' => $lender,
            'eligible' => $eligible,
            'matchScore' => $eligible ? $scorePoints + $amountPoints + $ratePoints + $employmentPoints : 0,
            'reasons' => $reasons,
        ];
    }
}
