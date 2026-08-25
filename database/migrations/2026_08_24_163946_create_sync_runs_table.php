<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sync_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('data_source_id')->constrained()->cascadeOnDelete();
            $table->string('status', 30)->index();
            $table->timestamp('started_at')->index();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('records_received')->default(0);
            $table->unsignedInteger('records_stored')->default(0);
            $table->text('error_message')->nullable();
            $table->json('context')->nullable();
            $table->timestamps();

            $table->index(['data_source_id', 'status', 'started_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_runs');
    }
};
