<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * High-Scale Database Optimization:
 * Adds targeted composite indexes for audit queries, fast statement retrieval,
 * and multi-tenant monthly snapshot batch processing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('statements', function (Blueprint $table) {
            $table->index(['user_id', 'status', 'created_at'], 'statements_user_status_created_idx');
        });

        Schema::table('debt_snapshots', function (Blueprint $table) {
            $table->index('snapshot_month', 'debt_snapshots_month_idx');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index(['actor_role', 'created_at'], 'audit_logs_role_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('statements', function (Blueprint $table) {
            $table->dropIndex('statements_user_status_created_idx');
        });

        Schema::table('debt_snapshots', function (Blueprint $table) {
            $table->dropIndex('debt_snapshots_month_idx');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('audit_logs_role_created_idx');
        });
    }
};
