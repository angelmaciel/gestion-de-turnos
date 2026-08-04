<?php

use App\Console\Commands\SnapshotRoomDailyStats;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Cierra las estadísticas del día anterior. Corre en el contenedor
// `scheduler` (docker-compose.yml), que solo ejecuta `schedule:work`.
Schedule::command(SnapshotRoomDailyStats::class)
    ->dailyAt('00:10')
    ->withoutOverlapping();
