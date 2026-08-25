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
        Schema::create('feature_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('data_source_id')->constrained()->cascadeOnDelete();
            $table->timestamp('calculated_at');
            $table->unsignedSmallInteger('horizon_hours')->default(24);
            $table->decimal('price', 36, 18);
            $table->decimal('change_pct', 12, 6);
            $table->decimal('realized_volatility_pct', 12, 6);
            $table->unsignedTinyInteger('risk_score');
            $table->string('risk_level', 20);
            $table->unsignedTinyInteger('data_completeness');
            $table->json('features');
            $table->string('algorithm_version', 40);
            $table->timestamps();

            $table->unique(
                ['asset_id', 'calculated_at', 'horizon_hours', 'algorithm_version'],
                'feature_snapshots_asset_time_horizon_version_unique',
            );
            $table->index(['asset_id', 'algorithm_version', 'calculated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feature_snapshots');
    }
};
