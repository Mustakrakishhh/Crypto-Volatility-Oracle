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
        Schema::table('volatility_forecasts', function (Blueprint $table) {
            $table->json('model_inputs')->nullable()->after('model_version');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('volatility_forecasts', function (Blueprint $table) {
            $table->dropColumn('model_inputs');
        });
    }
};
