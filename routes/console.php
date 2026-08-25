<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('oracle:sync-bybit --limit=3')
    ->hourlyAt(5)
    ->withoutOverlapping(20);

Schedule::command('oracle:build-baseline')
    ->hourlyAt(10)
    ->withoutOverlapping(20);

Schedule::command('oracle:sync-news --days=8')
    ->dailyAt('00:20')
    ->withoutOverlapping(30);

Schedule::command('oracle:build-news-model')
    ->dailyAt('00:35')
    ->withoutOverlapping(20);
