<?php

namespace Tests\Unit;

use App\Support\Financial;
use App\Support\Money;
use PHPUnit\Framework\TestCase;

class FinancialTest extends TestCase
{
    public function test_emi_for_standard_personal_loan(): void
    {
        // ₹5,00,000 at 14.5% for 36 months
        $this->assertSame(17210, Financial::emi(500000, 14.5, 36));
    }

    public function test_emi_with_zero_interest(): void
    {
        $this->assertSame(10000, Financial::emi(120000, 0, 12));
    }

    public function test_amortization_schedule_pays_off_the_loan(): void
    {
        $result = Financial::amortizationSchedule(100000, 12, 12);

        $this->assertCount(12, $result['schedule']);
        $this->assertSame(100000, $result['schedule'][0]['openingBalanceRupees']);
        $this->assertSame(0, $result['schedule'][11]['closingBalanceRupees']);
        $this->assertGreaterThan(0, $result['totalInterestRupees']);
        $this->assertSame(100000 + $result['totalInterestRupees'], $result['totalPaymentRupees']);
    }

    public function test_money_conversions(): void
    {
        $this->assertSame(5000050, Money::rupeesToPaise(50000.50));
        $this->assertSame(50000.5, Money::paiseToRupees(5000050));
        $this->assertSame(125000, Money::paiseToRupees(12500000));
        $this->assertSame(1075, Money::percentToBps(10.75));
        $this->assertSame(42, Money::bpsToPercent(4200));
        $this->assertSame(14.5, Money::bpsToPercent(1450));
    }

    public function test_round_matches_javascript_math_round(): void
    {
        $this->assertSame(3, Money::round(2.5));
        $this->assertSame(-2, Money::round(-2.5));
        $this->assertSame(17210, Money::round(17210.49));
    }

    public function test_indian_number_formatting(): void
    {
        $this->assertSame('1,25,000', Money::formatInr(125000));
        $this->assertSame('12,000', Money::formatInr(12000));
    }
}
