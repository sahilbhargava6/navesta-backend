<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Key figures read from a statement by the statement-parser service
 * (outstanding, minimum due, rate, due date...). Stored encrypted; the
 * transactions it can also return are not kept (data minimisation).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('statements', function (Blueprint $table) {
            $table->string('parsed_kind', 10)->nullable()->after('scanned_at');   // card | loan
            $table->text('parsed_fields')->nullable()->after('parsed_kind');      // encrypted JSON
            $table->boolean('parse_needs_review')->nullable()->after('parsed_fields');
            $table->string('parser_version', 20)->nullable()->after('parse_needs_review');
            $table->timestampTz('parsed_at')->nullable()->after('parser_version');
        });
    }

    public function down(): void
    {
        Schema::table('statements', function (Blueprint $table) {
            $table->dropColumn(['parsed_kind', 'parsed_fields', 'parse_needs_review', 'parser_version', 'parsed_at']);
        });
    }
};
