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
        Schema::create('volatility_forecasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('feature_snapshot_id')->constrained()->cascadeOnDelete();
            $table->timestamp('predicted_at');
            $table->timestamp('target_at');
            $table->unsignedSmallInteger('horizon_hours')->default(24);
            $table->decimal('predicted_volatility_pct', 12, 6);
            $table->decimal('lower_bound_pct', 12, 6)->nullable();
            $table->decimal('upper_bound_pct', 12, 6)->nullable();
            $table->decimal('confidence_pct', 6, 2)->nullable();
            $table->string('status', 20)->default('pending');
            $table->decimal('actual_volatility_pct', 12, 6)->nullable();
            $table->decimal('absolute_error', 12, 6)->nullable();
            $table->decimal('squared_error', 16, 8)->nullable();
            $table->timestamp('evaluated_at')->nullable();
            $table->string('model_version', 40);
            $table->timestamps();

            $table->unique(
                ['feature_snapshot_id', 'model_version'],
                'volatility_forecasts_snapshot_version_unique',
            );
            $table->index(['asset_id', 'model_version', 'predicted_at']);
            $table->index(['model_version', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('volatility_forecasts');
    }
};
