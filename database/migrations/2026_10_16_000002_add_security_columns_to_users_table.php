<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - phone becomes encrypted at rest (ciphertext needs a text column)
 * - phone verification (OTP) and optional OTP-at-login for customers
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('phone')->nullable()->change();
            $table->timestampTz('phone_verified_at')->nullable();
            $table->boolean('two_factor_enabled')->default(false);
        });

        // Encrypt existing plain-text numbers
        DB::table('users')->whereNotNull('phone')->orderBy('id')->each(function ($user) {
            if (! $this->isEncrypted($user->phone)) {
                DB::table('users')->where('id', $user->id)->update(['phone' => Crypt::encryptString($user->phone)]);
            }
        });
    }

    public function down(): void
    {
        DB::table('users')->whereNotNull('phone')->orderBy('id')->each(function ($user) {
            if ($this->isEncrypted($user->phone)) {
                DB::table('users')->where('id', $user->id)->update(['phone' => Crypt::decryptString($user->phone)]);
            }
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone_verified_at', 'two_factor_enabled']);
            $table->string('phone', 15)->nullable()->change();
        });
    }

    private function isEncrypted(string $value): bool
    {
        try {
            Crypt::decryptString($value);

            return true;
        } catch (Throwable) {
            return false;
        }
    }
};
