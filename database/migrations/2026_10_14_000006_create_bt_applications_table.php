<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bt_applications')) {
            return;
        }

        Schema::create('bt_applications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('lender_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('source_loan_id')->nullable()->constrained('loans')->nullOnDelete();
            $table->foreignUuid('source_cc_id')->nullable()->constrained('credit_cards')->nullOnDelete();
            $table->bigInteger('outstanding_paise');
            $table->integer('current_rate_bps');
            $table->integer('new_rate_bps')->nullable();
            $table->smallInteger('requested_tenure_months');
            $table->smallInteger('crif_score')->nullable();
            $table->string('status', 15)->default('draft'); // draft | submitted | approved | rejected | disbursed
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bt_applications');
    }
};
