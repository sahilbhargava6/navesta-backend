<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lenders')) {
            return;
        }

        Schema::create('lenders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 100)->unique();
            $table->string('type', 10); // bank | nbfc
            $table->char('initials', 2);
            $table->char('brand_color', 7)->nullable();
            $table->integer('min_rate_bps');
            $table->integer('max_rate_bps');
            $table->bigInteger('max_loan_amount_paise');
            $table->integer('processing_fee_bps');
            $table->smallInteger('disbursal_days_min');
            $table->smallInteger('disbursal_days_max');
            $table->boolean('is_active')->default(true);
            $table->timestampTz('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lenders');
    }
};
