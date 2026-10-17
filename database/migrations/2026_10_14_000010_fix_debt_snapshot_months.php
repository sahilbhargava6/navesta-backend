<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The Node backend built snapshot_month from local midnight on the 1st, which
 * landed on the last day of the previous month once stored as a UTC date
 * (e.g. October was saved as 2026-09-30). Move those rows to the 1st of the
 * month they were meant for, skipping any month that already has a correct row.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
            UPDATE debt_snapshots AS d
            SET snapshot_month = date_trunc('month', d.snapshot_month + interval '1 day')::date
            WHERE extract(day FROM d.snapshot_month) <> 1
              AND NOT EXISTS (
                  SELECT 1 FROM debt_snapshots AS o
                  WHERE o.user_id = d.user_id
                    AND o.snapshot_month = date_trunc('month', d.snapshot_month + interval '1 day')::date
              )
        SQL);
    }

    public function down(): void
    {
        // Data correction only; nothing to undo.
    }
};
