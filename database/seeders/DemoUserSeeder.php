<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Demo account: rahul.mehta@example.com / password123.
 * Safe to re-run; cards, loans and scores are only added the first time.
 */
class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'rahul.mehta@example.com'],
            [
                'name' => 'Rahul Mehta',
                'phone' => '+919876543210',
                'password_hash' => Hash::make('password123'),
                'property_status' => 'owned',
                'employment_sector' => 'pvt',
                'is_verified' => true,
            ],
        );

        if (! $user->creditCards()->exists()) {
            $user->creditCards()->createMany([
                ['bank_name' => 'HDFC Bank', 'card_name' => 'Millennia Credit Card', 'last_four' => '4321', 'credit_limit_paise' => 25000000, 'outstanding_paise' => 12500000, 'apr_bps' => 4200, 'min_payment_paise' => 625000, 'due_day' => 15],
                ['bank_name' => 'SBI Card', 'card_name' => 'SimplyCLICK', 'last_four' => '8765', 'credit_limit_paise' => 15000000, 'outstanding_paise' => 6500000, 'apr_bps' => 3900, 'min_payment_paise' => 325000, 'due_day' => 22],
            ]);
        }

        if (! $user->loans()->exists()) {
            $user->loans()->create([
                'lender_name' => 'Bajaj Finserv',
                'loan_type' => 'personal',
                'principal_paise' => 50000000,
                'outstanding_paise' => 38000000,
                'interest_rate_bps' => 1450,
                'emi_paise' => 1725000,
                'tenure_months' => 36,
                'tenure_remaining_months' => 24,
                'next_due_date' => '2026-10-10',
            ]);
        }

        if (! $user->creditScores()->exists()) {
            $user->creditScores()->create(['score' => 755, 'source' => 'cibil_api', 'fetched_at' => now()]);
        }

        $this->command->info("Seeded demo user {$user->email} (password: password123)");
    }
}
