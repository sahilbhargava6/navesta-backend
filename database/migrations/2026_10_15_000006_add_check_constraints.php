<?php

use App\Enums\BtStatus;
use App\Enums\CardStatus;
use App\Enums\CreditScoreSource;
use App\Enums\EmploymentSector;
use App\Enums\LenderType;
use App\Enums\LoanStatus;
use App\Enums\LoanType;
use App\Enums\PostStatus;
use App\Enums\PropertyStatus;
use App\Enums\StatementStatus;
use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Database-level guards so no client (including legacy code or manual SQL)
 * can store a status or type the application does not know about.
 * Postgres only; SQLite (tests) relies on validation and enum casts.
 */
return new class extends Migration
{
    private function constraints(): array
    {
        $in = fn (array $values) => "IN ('".implode("', '", $values)."')";

        return [
            ['users', 'users_role_check', 'role '.$in(UserRole::values())],
            ['users', 'users_property_status_check', 'property_status IS NULL OR property_status '.$in(PropertyStatus::values())],
            ['users', 'users_employment_sector_check', 'employment_sector IS NULL OR employment_sector '.$in(EmploymentSector::values())],
            ['credit_cards', 'credit_cards_status_check', 'status '.$in(CardStatus::values())],
            ['credit_cards', 'credit_cards_amounts_check', 'credit_limit_paise > 0 AND outstanding_paise >= 0 AND apr_bps > 0'],
            ['credit_cards', 'credit_cards_due_day_check', 'due_day IS NULL OR due_day BETWEEN 1 AND 31'],
            ['loans', 'loans_status_check', 'status '.$in(LoanStatus::values())],
            ['loans', 'loans_loan_type_check', 'loan_type '.$in(LoanType::values())],
            ['loans', 'loans_amounts_check', 'principal_paise > 0 AND outstanding_paise >= 0 AND emi_paise > 0 AND interest_rate_bps > 0'],
            ['lenders', 'lenders_type_check', 'type '.$in(LenderType::values())],
            ['lenders', 'lenders_min_credit_score_check', 'min_credit_score BETWEEN 300 AND 900'],
            ['bt_applications', 'bt_applications_status_check', 'status '.$in(BtStatus::values())],
            ['credit_scores', 'credit_scores_source_check', 'source '.$in(CreditScoreSource::values())],
            ['credit_scores', 'credit_scores_score_check', 'score BETWEEN 300 AND 900'],
            ['posts', 'posts_status_check', 'status '.$in(PostStatus::values())],
            ['statements', 'statements_status_check', 'status '.$in(StatementStatus::values())],
        ];
    }

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->constraints() as [$table, $name, $check]) {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$name}");
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$name} CHECK ({$check})");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->constraints() as [$table, $name]) {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$name}");
        }
    }
};
