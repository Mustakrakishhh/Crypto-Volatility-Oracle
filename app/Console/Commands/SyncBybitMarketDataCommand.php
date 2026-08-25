<?php

namespace App\Console\Commands;

use App\Actions\SyncBybitMarketData;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('oracle:sync-bybit {--limit=200 : Number of hourly candles per asset, from 1 to 1000} {--symbol=* : Limit the sync to one or more Bybit symbols}')]
#[Description('Synchronize public hourly market candles from Bybit')]
class SyncBybitMarketDataCommand extends Command
{
    public function handle(SyncBybitMarketData $syncBybitMarketData): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT);

        if ($limit === false || $limit < 1 || $limit > 1000) {
            $this->error('The --limit option must be an integer between 1 and 1000.');

            return self::FAILURE;
        }

        /** @var list<string> $symbols */
        $symbols = array_values(array_filter(
            (array) $this->option('symbol'),
            fn (mixed $symbol): bool => is_string($symbol) && $symbol !== '',
        ));

        try {
            $syncRun = $syncBybitMarketData->handle($limit, $symbols);
        } catch (Throwable $exception) {
            $this->error("Bybit synchronization failed: {$exception->getMessage()}");

            return self::FAILURE;
        }

        $this->info(
            "Bybit synchronization completed: {$syncRun->records_stored} of {$syncRun->records_received} candles stored.",
        );

        return self::SUCCESS;
    }
}
