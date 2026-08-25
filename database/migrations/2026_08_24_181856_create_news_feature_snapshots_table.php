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
        Schema::create('news_feature_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('data_source_id')->constrained()->cascadeOnDelete();
            $table->date('observed_on');
            $table->unsignedInteger('article_count');
            $table->unsignedBigInteger('monitored_article_count')->nullable();
            $table->decimal('coverage_share_pct', 14, 8)->nullable();
            $table->decimal('average_tone', 10, 6)->nullable();
            $table->string('query', 500);
            $table->string('provider_version', 40)->default('gdelt-doc-2');
            $table->timestamps();

            $table->unique(
                ['asset_id', 'data_source_id', 'observed_on'],
                'news_features_asset_source_date_unique',
            );
            $table->index(['asset_id', 'observed_on']);
            $table->index(['data_source_id', 'observed_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('news_feature_snapshots');
    }
};
