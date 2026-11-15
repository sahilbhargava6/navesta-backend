<?php

namespace App\Console\Commands;

use App\Enums\CreditScoreSource;
use App\Enums\UserRole;
use App\Models\Inquiry;
use App\Models\Lender;
use App\Models\User;
use App\Services\BalanceTransferService;
use App\Services\ConsentService;
use App\Services\StatementVault;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Demo logins for showing Navesta to a client: one admin and one customer
 * with sample cards, a loan and an application. Every run resets them and
 * prints new random passwords. Never runs in production, and the accounts
 * use reserved example.com addresses so they can't belong to anyone real.
 */
class DemoAccounts extends Command
{
    public const ADMIN_EMAIL = 'admin.demo@example.com';

    public const CUSTOMER_EMAIL = 'customer.demo@example.com';

    private const DEMO_PHONE = '+919000000001';

    protected $signature = 'navesta:demo-accounts {--delete : Remove the demo accounts and their data instead}';

    protected $description = 'Create or reset the client-demo admin and customer logins (not in production)';

    public function handle(ConsentService $consents, BalanceTransferService $transfers, StatementVault $vault): int
    {
        if (app()->environment('production')) {
            $this->error('Demo accounts are never created in production.');

            return self::FAILURE;
        }

        // Start from a clean slate either way (cascades remove their cards, loans, applications...)
        foreach (User::whereIn('email', [self::ADMIN_EMAIL, self::CUSTOMER_EMAIL])->get() as $old) {
            foreach ($old->statements()->get() as $statement) {
                $vault->delete($statement);
            }
            $old->tokens()->delete();
            $old->delete();
        }
        Inquiry::where('message', 'like', '[Demo]%')->delete();

        if ($this->option('delete')) {
            $this->info('Demo accounts and their data removed.');

            return self::SUCCESS;
        }

        if (! Lender::query()->exists()) {
            $this->error('No lenders found. Run: php artisan db:seed --class=LenderSeeder');

            return self::FAILURE;
        }

        $adminPassword = $this->password();
        $customerPassword = $this->password();

        DB::transaction(function () use ($consents, $transfers, $adminPassword, $customerPassword) {
            $admin = User::create([
                'name' => 'Demo Admin',
                'email' => self::ADMIN_EMAIL,
                'password_hash' => Hash::make($adminPassword),
            ]);
            $admin->forceFill(['role' => UserRole::Admin, 'is_verified' => true])->save();

            $customer = User::create([
                'name' => 'Priya Sharma',
                'email' => self::CUSTOMER_EMAIL,
                'phone' => self::DEMO_PHONE,
                'password_hash' => Hash::make($customerPassword),
                'employment_sector' => 'pvt',
                'property_status' => 'rented',
                'reminders_enabled' => true,
            ]);
            $customer->forceFill(['is_verified' => true, 'phone_verified_at' => now()])->save();
            foreach (['terms' => true, 'privacy' => true, 'marketing' => false, 'reminders' => true] as $purpose => $granted) {
                $consents->record($customer, $purpose, $granted);
            }

            $customer->creditScores()->create(['score' => 742, 'source' => CreditScoreSource::UserInput, 'fetched_at' => now()]);

            $hdfc = $customer->creditCards()->create([
                'bank_name' => 'HDFC Bank', 'card_name' => 'Regalia', 'last_four' => '4821',
                'credit_limit_paise' => 30000000, 'outstanding_paise' => 18450000, 'apr_bps' => 4200,
                'min_payment_paise' => 922500, 'due_day' => 5,
            ]);
            $customer->creditCards()->create([
                'bank_name' => 'SBI Card', 'card_name' => 'SimplyCLICK', 'last_four' => '1907',
                'credit_limit_paise' => 15000000, 'outstanding_paise' => 6200000, 'apr_bps' => 4290,
                'min_payment_paise' => 310000, 'due_day' => 18,
            ]);
            $customer->loans()->create([
                'lender_name' => 'Bajaj Finserv', 'loan_type' => 'personal',
                'principal_paise' => 50000000, 'outstanding_paise' => 31200000, 'interest_rate_bps' => 1450,
                'emi_paise' => 1172000, 'tenure_months' => 48, 'tenure_remaining_months' => 30,
            ]);

            // One submitted balance-transfer application for the admin to review
            $lender = Lender::query()->orderBy('min_rate_bps')->first();
            $transfers->apply($customer, ['lenderId' => $lender->id, 'sourceCcId' => $hdfc->id, 'requestedTenureMonths' => 24]);

            // Two website leads for the admin dashboard
            Inquiry::create([
                'phone' => '+919000000002', 'inquiry_type' => 'Personal Loan Callback', 'status' => 'pending',
                'message' => '[Demo] Personal loan quick apply from the home page. Please call back on +91 9000000002.',
            ]);
            Inquiry::create([
                'name' => 'Rahul Verma', 'email' => 'rahul.demo@example.com', 'inquiry_type' => 'General Inquiry', 'status' => 'pending',
                'message' => '[Demo] How does a balance transfer reduce my credit card interest?',
            ]);
        });

        $site = rtrim((string) config('navesta.frontend_url', 'http://localhost:3000'), '/');
        $this->newLine();
        $this->info('Demo accounts are ready. Passwords change every time this command runs.');
        $this->table(['Login', 'Where', 'Email', 'Password'], [
            ['Admin panel', rtrim((string) config('app.url'), '/').'/admin', self::ADMIN_EMAIL, $adminPassword],
            ['Customer', $site.' → Login (email + password)', self::CUSTOMER_EMAIL, $customerPassword],
        ]);
        $this->line('The admin must set up an authenticator app (Google Authenticator, Authy…) at first sign-in.');
        $this->line('Remove them with: php artisan navesta:demo-accounts --delete');

        return self::SUCCESS;
    }

    /** 16 characters with letters, digits and a symbol (meets the admin password rule). */
    private function password(): string
    {
        $symbols = '!@#%*-_+?';

        return Str::password(14, symbols: false).random_int(10, 99).$symbols[random_int(0, strlen($symbols) - 1)];
    }
}
