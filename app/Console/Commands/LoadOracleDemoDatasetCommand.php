<?php

namespace App\Console\Commands;

use App\Actions\LoadOracleDemoDataset;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use JsonException;
use Throwable;

#[Signature('oracle:load-demo {--path=database/fixtures/oracle-demo.json : Source JSON path}')]
#[Description('Load the portable demo dataset and build both forecast models')]
class LoadOracleDemoDatasetCommand extends Command
{
    public function handle(LoadOracleDemoDataset $loadOracleDemoDataset): int
    {
        $pathOption = $this->option('path');

        if (! is_string($pathOption) || $pathOption === '') {
            $this->error('The --path option must be a non-empty path.');

            return self::FAILURE;
        }

        $path = $this->absolutePath($pathOption);

        if (! File::isFile($path)) {
            $this->error("Demo dataset not found: {$path}");

            return self::FAILURE;
        }

        try {
            $json = File::get($path);
            $dataset = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

            if (! is_array($dataset)) {
                throw new JsonException('The demo dataset root must be a JSON object.');
            }

            $summary = $loadOracleDemoDataset->handle($dataset, hash('sha256', $json));
        } catch (Throwable $exception) {
            $this->error("Demo dataset import failed: {$exception->getMessage()}");

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Demo dataset loaded: %d raw records, %d candles, %d news features, %d baseline and %d news forecasts.',
            $summary['source_records'],
            $summary['market_candles'],
            $summary['news_features'],
            $summary['baseline_forecasts'],
            $summary['news_forecasts'],
        ));

        return self::SUCCESS;
    }

    private function absolutePath(string $path): string
    {
        return preg_match('/^(?:[A-Za-z]:[\\\\\/]|[\\\\\/])/', $path) === 1
            ? $path
            : base_path($path);
    }
}
