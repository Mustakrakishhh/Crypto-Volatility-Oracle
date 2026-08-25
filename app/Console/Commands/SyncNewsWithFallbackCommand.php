<?php

namespace App\Console\Commands;

use App\Actions\SyncAlphaVantageNewsFeatures;
use App\Actions\SyncGdeltNewsFeatures;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('oracle:sync-news {--days=30 : Number of daily intervals, from 8 to 365} {--end-date= : Inclusive range end in YYYY-MM-DD format (UTC)} {--symbol=* : Limit the sync to one or more asset symbols}')]
#[Description('Synchronize news from GDELT with automatic Alpha Vantage fallback')]
class SyncNewsWithFallbackCommand extends Command
{
    public function handle(
        SyncGdeltNewsFeatures $syncGdeltNewsFeatures,
        SyncAlphaVantageNewsFeatures $syncAlphaVantageNewsFeatures,
    ): int {
        $days = filter_var($this->option('days'), FILTER_VALIDATE_INT);

        if ($days === false || $days < 8 || $days > 365) {
            $this->error('The --days option must be an integer between 8 and 365.');

            return self::FAILURE;
        }

        $rangeEnd = $this->rangeEnd();

        if ($rangeEnd === false) {
            return self::FAILURE;
        }

        /** @var list<string> $symbols */
        $symbols = array_values(array_filter(
            (array) $this->option('symbol'),
            fn (mixed $symbol): bool => is_string($symbol) && $symbol !== '',
        ));

        try {
            $syncRun = $syncGdeltNewsFeatures->handle($days, $symbols, $rangeEnd);
            $this->info(
                "GDELT synchronization completed: {$syncRun->records_stored} daily features stored.",
            );

            return self::SUCCESS;
        } catch (Throwable $gdeltException) {
            $this->warn("GDELT unavailable: {$gdeltException->getMessage()}");
            $this->warn('Switching to Alpha Vantage fallback.');
        }

        try {
            $syncRun = $syncAlphaVantageNewsFeatures->handle($days, $symbols, $rangeEnd);
        } catch (Throwable $alphaVantageException) {
            $this->error("Both news sources failed. Alpha Vantage: {$alphaVantageException->getMessage()}");

            return self::FAILURE;
        }

        $this->info(
            "Alpha Vantage fallback completed: {$syncRun->records_stored} daily features stored from {$syncRun->records_received} articles.",
        );

        return self::SUCCESS;
    }

    private function rangeEnd(): CarbonImmutable|false|null
    {
        $endDateOption = $this->option('end-date');

        if ($endDateOption === null) {
            return null;
        }

        if (! CarbonImmutable::hasFormat($endDateOption, 'Y-m-d')) {
            $this->error('The --end-date option must use the YYYY-MM-DD format.');

            return false;
        }

        return CarbonImmutable::createFromFormat('!Y-m-d', $endDateOption, 'UTC');
    }
}
