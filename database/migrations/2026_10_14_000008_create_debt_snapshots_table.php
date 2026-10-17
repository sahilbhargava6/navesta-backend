<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('debt_snapshots')) {
            return;
        }

        Schema::create('debt_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->date('snapshot_month');
            $table->bigInteger('cc_outstanding_paise')->default(0);
            $table->bigInteger('loan_outstanding_paise')->default(0);
            $table->bigInteger('total_emi_paise')->default(0);
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['user_id', 'snapshot_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('debt_snapshots');
    }
};
