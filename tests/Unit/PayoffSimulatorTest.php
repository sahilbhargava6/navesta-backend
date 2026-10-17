<?php

namespace Tests\Unit;

use App\Support\Financial;
use App\Support\PayoffSimulator;
use PHPUnit\Framework\TestCase;

class PayoffSimulatorTest extends TestCase
{
    public function test_single_loan_paid_by_its_emi_clears_in_its_tenure(): void
    {
        $emi = Financial::emi(100000, 12, 12);

        $result = PayoffSimulator::run([['id' => 'a', 'balance' => 100000, 'ratePercent' => 12, 'minPayment' => $emi]]);

        $this->assertSame(12, $result['months']);
        $this->assertSame(12, $result['payoffMonth']['a']);
        $this->assertEqualsWithDelta(6619, $result['totalInterest'], 5); // ≈ EMI × 12 − principal
    }

    public function test_rollover_pays_the_target_faster_and_saves_interest(): void
    {
        $debts = [
            ['id' => 'card', 'balance' => 50000, 'ratePercent' => 42, 'minPayment' => 2500],
            ['id' => 'loan', 'balance' => 200000, 'ratePercent' => 12, 'minPayment' => 6000],
        ];

        $minimumsOnly = PayoffSimulator::run($debts, rollover: false);
        $avalanche = PayoffSimulator::run($debts, rollover: true);

        $this->assertLessThan($minimumsOnly['totalInterest'], $avalanche['totalInterest']);
        $this->assertLessThan($minimumsOnly['months'], $avalanche['months']);
    }

    public function test_extra_payment_goes_to_the_first_debt_in_order(): void
    {
        $debts = [
            ['id' => 'first', 'balance' => 10000, 'ratePercent' => 0, 'minPayment' => 1000],
            ['id' => 'second', 'balance' => 10000, 'ratePercent' => 0, 'minPayment' => 1000],
        ];

        $result = PayoffSimulator::run($debts, extraMonthly: 3000);

        // 4,000/month on the first debt clears it in 3 months; then 5,000/month clears the rest
        $this->assertSame(3, $result['payoffMonth']['first']);
        $this->assertSame(4, $result['payoffMonth']['second']);
    }

    public function test_a_payment_below_the_interest_never_finishes(): void
    {
        $result = PayoffSimulator::run(
            [['id' => 'x', 'balance' => 100000, 'ratePercent' => 36, 'minPayment' => 1000]], // interest is 3,000/month
            rollover: false,
            maxMonths: 120,
        );

        $this->assertNull($result['months']);
        $this->assertNull($result['payoffMonth']['x']);
    }

    public function test_already_cleared_debt_counts_as_paid(): void
    {
        $result = PayoffSimulator::run([['id' => 'z', 'balance' => 0, 'ratePercent' => 10, 'minPayment' => 100]]);

        $this->assertSame(0, $result['months']);
        $this->assertSame(0, $result['payoffMonth']['z']);
    }
}
