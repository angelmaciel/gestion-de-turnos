<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Professional;
use App\Models\Room;
use App\Models\RoomDailyStat;
use Illuminate\Support\Carbon;

/**
 * Genera (o recalcula) la fila de room_daily_stats de una fecha dada.
 *
 * Es la base de historial para las fases futuras (predicción, ranking de
 * especialidades): guarda sumas y conteos crudos, no promedios, para que esas
 * fases puedan calcular la fórmula que necesiten sin haber perdido precisión.
 *
 * Idempotente por diseño (updateOrCreate): correrlo dos veces para el mismo
 * día no duplica filas, solo recalcula.
 */
class RoomDailyStatsService
{
    public function generarParaFecha(Carbon $fecha): void
    {
        Room::query()->get()
            ->each(fn (Room $room) => $this->generarParaSala($room, $fecha));
    }

    private function generarParaSala(Room $room, Carbon $fecha): void
    {
        // Ver comentario equivalente en ControlTowerService: se busca por
        // professionals.room_id, no por el rooms.professional_id denormalizado.
        $specialtyId = Professional::query()->where('room_id', $room->id)->value('specialty_id');

        if ($specialtyId === null) {
            return;
        }

        $turnos = Appointment::query()
            ->where('specialty_id', $specialtyId)
            ->whereDate('daily_date', $fecha->toDateString())
            ->get();

        $llamados = $turnos->whereNotNull('called_at');
        $atendidos = $turnos->where('status', AppointmentStatus::ATENDIDO);
        $ausentes = $turnos->where('status', AppointmentStatus::AUSENTE);

        $esperas = $llamados
            ->filter(fn (Appointment $t) => $t->preconsulta_at !== null)
            ->map(fn (Appointment $t) => $t->preconsulta_at->diffInSeconds($t->called_at));

        $duracionesConsulta = $atendidos
            ->filter(fn (Appointment $t) => $t->called_at !== null && $t->attended_at !== null)
            ->map(fn (Appointment $t) => $t->called_at->diffInSeconds($t->attended_at));

        RoomDailyStat::updateOrCreate(
            ['room_id' => $room->id, 'date' => $fecha->toDateString()],
            [
                'specialty_id' => $specialtyId,
                'atendidos_count' => $atendidos->count(),
                'ausentes_count' => $ausentes->count(),
                'llamados_count' => $llamados->count(),
                'reintentos_count' => $llamados->sum(fn (Appointment $t) => max(0, $t->attempts - 1)),
                'tiempo_espera_total_segundos' => (int) $esperas->sum(),
                'tiempo_espera_muestras' => $esperas->count(),
                'duracion_consulta_total_segundos' => (int) $duracionesConsulta->sum(),
                'duracion_consulta_muestras' => $duracionesConsulta->count(),
                'primer_llamado_at' => $llamados->min('called_at'),
                'ultimo_atendido_at' => $atendidos->max('attended_at'),
                'generado_at' => now(),
            ]
        );
    }
}
