<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('customer')->after('employment_sector');
            $table->boolean('reminders_enabled')->default(true)->after('is_verified');
            $table->string('remember_token', 100)->nullable();
            // Admin two-factor login (Filament app authentication), stored encrypted
            $table->text('app_authentication_secret')->nullable();
            $table->text('app_authentication_recovery_codes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'reminders_enabled', 'remember_token', 'app_authentication_secret', 'app_authentication_recovery_codes']);
        });
    }
};
