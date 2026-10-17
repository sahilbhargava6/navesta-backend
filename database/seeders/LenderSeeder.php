<?php

namespace Database\Seeders;

use App\Models\Lender;
use Illuminate\Database\Seeder;

/**
 * Partner lenders (4 banks + 4 NBFCs). Rates in basis points, amounts in paise.
 */
class LenderSeeder extends Seeder
{
    public function run(): void
    {
        $lenders = [
            ['name' => 'HDFC Bank', 'type' => 'bank', 'initials' => 'HB', 'brand_color' => '#1D4ED8', 'min_rate_bps' => 850, 'max_rate_bps' => 1450, 'max_loan_amount_paise' => 500000000, 'processing_fee_bps' => 100, 'min_credit_score' => 725, 'disbursal_days_min' => 1, 'disbursal_days_max' => 3],
            ['name' => 'State Bank of India', 'type' => 'bank', 'initials' => 'SB', 'brand_color' => '#0284C7', 'min_rate_bps' => 875, 'max_rate_bps' => 1350, 'max_loan_amount_paise' => 400000000, 'processing_fee_bps' => 50, 'min_credit_score' => 700, 'disbursal_days_min' => 2, 'disbursal_days_max' => 5],
            ['name' => 'ICICI Bank', 'type' => 'bank', 'initials' => 'IB', 'brand_color' => '#C2410C', 'min_rate_bps' => 890, 'max_rate_bps' => 1500, 'max_loan_amount_paise' => 500000000, 'processing_fee_bps' => 125, 'min_credit_score' => 725, 'disbursal_days_min' => 1, 'disbursal_days_max' => 2],
            ['name' => 'Axis Bank', 'type' => 'bank', 'initials' => 'AB', 'brand_color' => '#991B1B', 'min_rate_bps' => 900, 'max_rate_bps' => 1550, 'max_loan_amount_paise' => 450000000, 'processing_fee_bps' => 100, 'min_credit_score' => 700, 'disbursal_days_min' => 1, 'disbursal_days_max' => 3],
            ['name' => 'Bajaj Finserv', 'type' => 'nbfc', 'initials' => 'BF', 'brand_color' => '#0369A1', 'min_rate_bps' => 1050, 'max_rate_bps' => 1800, 'max_loan_amount_paise' => 350000000, 'processing_fee_bps' => 150, 'min_credit_score' => 685, 'disbursal_days_min' => 0, 'disbursal_days_max' => 1],
            ['name' => 'Tata Capital', 'type' => 'nbfc', 'initials' => 'TC', 'brand_color' => '#1E3A8A', 'min_rate_bps' => 1099, 'max_rate_bps' => 1900, 'max_loan_amount_paise' => 300000000, 'processing_fee_bps' => 150, 'min_credit_score' => 675, 'disbursal_days_min' => 1, 'disbursal_days_max' => 2],
            ['name' => 'Aditya Birla Capital', 'type' => 'nbfc', 'initials' => 'AC', 'brand_color' => '#B91C1C', 'min_rate_bps' => 1125, 'max_rate_bps' => 1950, 'max_loan_amount_paise' => 250000000, 'processing_fee_bps' => 175, 'min_credit_score' => 660, 'disbursal_days_min' => 1, 'disbursal_days_max' => 3],
            ['name' => 'Muthoot Finance', 'type' => 'nbfc', 'initials' => 'MF', 'brand_color' => '#D97706', 'min_rate_bps' => 1199, 'max_rate_bps' => 2100, 'max_loan_amount_paise' => 200000000, 'processing_fee_bps' => 200, 'min_credit_score' => 650, 'disbursal_days_min' => 0, 'disbursal_days_max' => 1],
        ];

        foreach ($lenders as $lender) {
            Lender::updateOrCreate(['name' => $lender['name']], $lender);
        }

        $this->command->info('Seeded '.count($lenders).' lenders');
    }
}
