<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('credit_cards')) {
            return;
        }

        Schema::create('credit_cards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('bank_name', 80);
            $table->string('card_name', 120);
            $table->char('last_four', 4)->nullable();
            $table->bigInteger('credit_limit_paise');
            $table->bigInteger('outstanding_paise')->default(0);
            $table->integer('apr_bps');
            $table->bigInteger('min_payment_paise')->nullable();
            $table->smallInteger('due_day')->nullable();
            $table->string('status', 10)->default('active'); // active | closed
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_cards');
    }
};
