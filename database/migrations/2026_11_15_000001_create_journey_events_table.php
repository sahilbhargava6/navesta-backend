<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Step reached" events from the website journeys (see App\Support\JourneyCatalog):
 * which journey, which step, when. No amounts or other figures are stored.
 * Signed-in customers are linked to their account (erased with it);
 * visitors are counted by a random browser id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journey_events', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->uuid('visitor_id')->nullable();
            $table->string('journey', 30);
            $table->string('step', 40);
            $table->string('detail', 40)->nullable(); // e.g. the life event, or the path picked
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['journey', 'step', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index('visitor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journey_events');
    }
};
