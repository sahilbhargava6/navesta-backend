<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Callback leads from the landing page ("Apply for a Personal Loan") only
 * carry a phone number, so name and email can no longer be mandatory.
 * The controller still requires an email or a phone on every inquiry.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inquiries', function (Blueprint $table) {
            $table->string('name', 120)->nullable()->change();
            $table->string('email', 120)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('inquiries', function (Blueprint $table) {
            $table->string('name', 120)->nullable(false)->change();
            $table->string('email', 120)->nullable(false)->change();
        });
    }
};
