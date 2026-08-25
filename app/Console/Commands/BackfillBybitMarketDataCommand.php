<?php

namespace App\Console\Commands;

use App\Actions\BackfillBybitMarketData;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('oracle:backfill-bybit {--days=90 : Historical depth in days, from 2 to 365} {--page-size=1000 : Candles per Bybit request, from 1 to 1000} {--symbol=* : Limit the backfill to one or more Bybit symbols}')]
#[Description('Backfill public hourly market candles from Bybit')]
class BackfillBybitMarketDataCommand extends Command
{
    public function handle(BackfillBybitMarketData $backfillBybitMarketData): int
    {
        $days = filter_var($this->option('days'), FILTER_VALIDATE_INT);
        $pageSize = filter_var($this->option('page-size'), FILTER_VALIDATE_INT);

        if ($days === false || $days < 2 || $days > 365) {
            $this->error('The --days option must be an integer between 2 and 365.');

            return self::FAILURE;
        }

        if ($pageSize === false || $pageSize < 1 || $pageSize > 1000) {
            $this->error('The --page-size option must be an integer between 1 and 1000.');

            return self::FAILURE;
        }

        /** @var list<string> $symbols */
        $symbols = array_values(array_filter(
            (array) $this->option('symbol'),
            fn (mixed $symbol): bool => is_string($symbol) && $symbol !== '',
        ));

        try {
            $syncRun = $backfillBybitMarketData->handle($days, $symbols, $pageSize);
        } catch (Throwable $exception) {
            $this->error("Bybit backfill failed: {$exception->getMessage()}");

            return self::FAILURE;
        }

        $pages = (int) data_get($syncRun->context, 'pages', 0);
        $this->info(
            "Bybit backfill completed: {$syncRun->records_stored} candles processed across {$pages} pages.",
        );

        return self::SUCCESS;
    }
}
