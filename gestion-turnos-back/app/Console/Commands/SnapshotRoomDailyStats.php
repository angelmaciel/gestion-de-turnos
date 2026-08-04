<?php

namespace App\Console\Commands;

use App\Services\RoomDailyStatsService;
use Carbon\CarbonPeriod;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Cierra el día en room_daily_stats. Corre sola vía el scheduler poco después
 * de medianoche (ver routes/console.php); --date/--from/--to son para
 * previsualizar o reprocesar a mano.
 */
class SnapshotRoomDailyStats extends Command
{
    protected $signature = 'control-tower:snapshot-stats {--date=} {--from=} {--to=}';

    protected $description = 'Calcula y guarda (o recalcula) las estadísticas diarias por sala.';

    public function handle(RoomDailyStatsService $service): int
    {
        if ($this->option('from') || $this->option('to')) {
            $desde = Carbon::parse($this->option('from') ?? $this->option('to'));
            $hasta = Carbon::parse($this->option('to') ?? $this->option('from'));

            foreach (CarbonPeriod::create($desde, $hasta) as $dia) {
                $service->generarParaFecha($dia);
                $this->line("Snapshot generado para {$dia->toDateString()}");
            }

            return self::SUCCESS;
        }

        $fecha = $this->option('date') ? Carbon::parse($this->option('date')) : today()->subDay();
        $service->generarParaFecha($fecha);
        $this->info("Snapshot generado para {$fecha->toDateString()}");

        return self::SUCCESS;
    }
}
