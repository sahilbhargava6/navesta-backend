<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Navesta tables were originally created by Prisma. Each create migration
 * skips tables that already exist, so the API can adopt the existing database
 * while still being able to build a fresh one from scratch.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            return;
        }

        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 120);
            $table->string('email', 200)->unique();
            $table->string('phone', 15)->nullable();
            $table->text('password_hash');
            $table->string('property_status', 10)->nullable();   // owned | rented
            $table->string('employment_sector', 10)->nullable(); // govt | pvt | self
            $table->boolean('is_verified')->default(false);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
