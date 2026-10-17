<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('statements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('original_name', 255);
            $table->string('disk', 40);
            $table->string('path', 500)->nullable(); // null once the file is deleted (e.g. infected)
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64);
            $table->string('status', 20)->default('pending_scan');
            $table->string('scan_signature', 255)->nullable();
            $table->timestampTz('scanned_at')->nullable();
            $table->timestampsTz();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statements');
    }
};
