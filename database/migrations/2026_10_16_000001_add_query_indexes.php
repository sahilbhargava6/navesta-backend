<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Postgres does not index foreign keys automatically, so every "my cards",
 * "my loans" and dashboard query was scanning whole tables. These match the
 * queries the API and admin panel actually run.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_cards', fn (Blueprint $t) => $t->index(['user_id', 'status']));
        Schema::table('loans', function (Blueprint $t) {
            $t->index(['user_id', 'status']);
            $t->index(['status', 'next_due_date']); // daily reminder job
        });
        Schema::table('credit_cards', fn (Blueprint $t) => $t->index(['status', 'due_day'])); // daily reminder job
        Schema::table('bt_applications', function (Blueprint $t) {
            $t->index(['user_id', 'created_at']);
            $t->index(['status', 'created_at']); // admin review queue
            $t->index('lender_id');
        });
        Schema::table('credit_scores', fn (Blueprint $t) => $t->index(['user_id', 'fetched_at']));
        Schema::table('users', fn (Blueprint $t) => $t->index(['role', 'created_at']));
    }

    public function down(): void
    {
        Schema::table('credit_cards', function (Blueprint $t) {
            $t->dropIndex(['user_id', 'status']);
            $t->dropIndex(['status', 'due_day']);
        });
        Schema::table('loans', function (Blueprint $t) {
            $t->dropIndex(['user_id', 'status']);
            $t->dropIndex(['status', 'next_due_date']);
        });
        Schema::table('bt_applications', function (Blueprint $t) {
            $t->dropIndex(['user_id', 'created_at']);
            $t->dropIndex(['status', 'created_at']);
            $t->dropIndex(['lender_id']);
        });
        Schema::table('credit_scores', fn (Blueprint $t) => $t->dropIndex(['user_id', 'fetched_at']));
        Schema::table('users', fn (Blueprint $t) => $t->dropIndex(['role', 'created_at']));
    }
};
