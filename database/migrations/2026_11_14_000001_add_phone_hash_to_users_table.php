<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phones are encrypted at rest, so they cannot be searched. A keyed hash
 * (HMAC with the app key) of the normalised number lets phone sign-in find
 * the account with an index instead of decrypting every user.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->char('phone_hash', 64)->nullable()->index();
        });

        DB::table('users')->whereNotNull('phone')->orderBy('id')->each(function ($row) {
            try {
                $phone = Crypt::decryptString($row->phone);
            } catch (Throwable) {
                return; // unreadable (wrong key): leave it for a manual fix
            }
            DB::table('users')->where('id', $row->id)->update(['phone_hash' => User::hashPhone($phone)]);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('phone_hash');
        });
    }
};
