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
        Schema::create('market_candles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('data_source_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('interval_minutes');
            $table->timestamp('opened_at');
            $table->timestamp('closed_at');
            $table->decimal('open', 36, 18);
            $table->decimal('high', 36, 18);
            $table->decimal('low', 36, 18);
            $table->decimal('close', 36, 18);
            $table->decimal('volume', 36, 18);
            $table->decimal('turnover', 36, 18);
            $table->boolean('is_closed')->default(false);
            $table->timestamps();

            $table->unique(
                ['asset_id', 'data_source_id', 'interval_minutes', 'opened_at'],
                'market_candles_asset_source_interval_open_unique',
            );
            $table->index(['asset_id', 'interval_minutes', 'opened_at']);
            $table->index(['data_source_id', 'is_closed']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('market_candles');
    }
};
