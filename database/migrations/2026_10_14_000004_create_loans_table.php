<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('loans')) {
            return;
        }

        Schema::create('loans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('lender_name', 100);
            $table->string('loan_type', 20); // personal | home | auto | education | other
            $table->bigInteger('principal_paise');
            $table->bigInteger('outstanding_paise');
            $table->integer('interest_rate_bps');
            $table->bigInteger('emi_paise');
            $table->smallInteger('tenure_months');
            $table->smallInteger('tenure_remaining_months');
            $table->date('next_due_date')->nullable();
            $table->string('status', 15)->default('active'); // active | closed | defaulted
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
