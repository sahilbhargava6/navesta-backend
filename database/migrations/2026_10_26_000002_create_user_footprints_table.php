<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_footprints', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_id')->nullable();
            $table->string('event_type', 50); // login, visit, consent, loan_application, export
            $table->string('ip_address', 45);
            $table->string('user_agent', 500)->nullable();
            $table->string('device_type', 30)->nullable(); // mobile, desktop, tablet
            $table->string('browser', 50)->nullable();
            $table->string('os', 50)->nullable();
            $table->string('fingerprint_hash', 64)->nullable(); // client canvas/hardware hash
            $table->string('session_id', 100)->nullable();
            $table->string('country_code', 10)->nullable();
            $table->string('city', 100)->nullable();
            $table->jsonb('cookie_consent')->nullable();
            $table->jsonb('headers_snapshot')->nullable(); // Sec-CH-UA, Accept-Language, etc.
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index(['event_type', 'created_at']);
            $table->index('ip_address');
            $table->index('fingerprint_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_footprints');
    }
};
