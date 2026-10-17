<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lenders', function (Blueprint $table) {
            // Lowest bureau score the lender accepts for a balance transfer
            $table->smallInteger('min_credit_score')->default(650)->after('processing_fee_bps');
        });
    }

    public function down(): void
    {
        Schema::table('lenders', function (Blueprint $table) {
            $table->dropColumn('min_credit_score');
        });
    }
};
