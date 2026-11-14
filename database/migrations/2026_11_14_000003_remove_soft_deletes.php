<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Deleting customer data must erase it (DPDP Act, right to erasure), not hide
 * it behind a `deleted_at` flag. Anything already soft-deleted is erased here
 * (statement files included), then the flag columns are dropped.
 */
return new class extends Migration
{
    private const TABLES = ['bt_applications', 'statements', 'loans', 'credit_cards', 'users'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasColumn($table, 'deleted_at')) {
                continue;
            }

            if ($table === 'statements') {
                DB::table('statements')->whereNotNull('deleted_at')->orderBy('id')
                    ->each(fn ($s) => $s->path ? Storage::disk($s->disk)->delete($s->path) : null);
            }

            if ($table === 'users') {
                $ids = DB::table('users')->whereNotNull('deleted_at')->pluck('id');
                // Files of statements owned by these users, then tokens and footprints
                // (neither has a cascading foreign key); other tables cascade.
                DB::table('statements')->whereIn('user_id', $ids)->orderBy('id')
                    ->each(fn ($s) => $s->path ? Storage::disk($s->disk)->delete($s->path) : null);
                DB::table('personal_access_tokens')->where('tokenable_type', 'App\\Models\\User')->whereIn('tokenable_id', $ids)->delete();
                DB::table('user_footprints')->whereIn('user_id', $ids)->delete();
            }

            DB::table($table)->whereNotNull('deleted_at')->delete();

            Schema::table($table, fn (Blueprint $t) => $t->dropSoftDeletesTz());
        }
    }

    public function down(): void
    {
        // Erased rows cannot come back; this only restores the columns.
        foreach (self::TABLES as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->softDeletesTz());
        }
    }
};
