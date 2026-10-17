<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('credit_scores')) {
            return;
        }

        Schema::create('credit_scores', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->smallInteger('score');
            $table->string('source', 20)->default('user_input'); // user_input | crif_api | cibil_api
            $table->timestampTz('fetched_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_scores');
    }
};
