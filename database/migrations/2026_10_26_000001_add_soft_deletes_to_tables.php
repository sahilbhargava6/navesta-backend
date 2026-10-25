<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->softDeletesTz();
        });

        Schema::table('credit_cards', function (Blueprint $table) {
            $table->softDeletesTz();
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->softDeletesTz();
        });

        Schema::table('bt_applications', function (Blueprint $table) {
            $table->softDeletesTz();
        });

        Schema::table('statements', function (Blueprint $table) {
            $table->softDeletesTz();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletesTz();
        });

        Schema::table('credit_cards', function (Blueprint $table) {
            $table->dropSoftDeletesTz();
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->dropSoftDeletesTz();
        });

        Schema::table('bt_applications', function (Blueprint $table) {
            $table->dropSoftDeletesTz();
        });

        Schema::table('statements', function (Blueprint $table) {
            $table->dropSoftDeletesTz();
        });
    }
};
