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
        Schema::create('source_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('data_source_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('sync_run_id')->nullable()->constrained()->nullOnDelete();
            $table->string('record_type', 40);
            $table->string('provider_key', 191);
            $table->timestamp('observed_at');
            $table->json('payload');
            $table->char('checksum', 64);
            $table->timestamps();

            $table->unique(
                ['data_source_id', 'record_type', 'provider_key'],
                'source_records_source_type_provider_unique',
            );
            $table->index(['asset_id', 'record_type', 'observed_at']);
            $table->index(['sync_run_id', 'observed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('source_records');
    }
};
