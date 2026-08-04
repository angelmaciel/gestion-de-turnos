<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Estadística diaria por sala. La genera y recalcula
 * App\Services\RoomDailyStatsService; ninguna otra parte del sistema debe
 * escribir acá directamente.
 *
 * @property int $id
 * @property int $room_id
 * @property int $specialty_id
 * @property Carbon $date
 * @property int $atendidos_count
 * @property int $ausentes_count
 * @property int $llamados_count
 * @property int $reintentos_count
 * @property int $tiempo_espera_total_segundos
 * @property int $tiempo_espera_muestras
 * @property int $duracion_consulta_total_segundos
 * @property int $duracion_consulta_muestras
 * @property Carbon|null $primer_llamado_at
 * @property Carbon|null $ultimo_atendido_at
 * @property Carbon|null $generado_at
 * @property-read Room $room
 * @property-read Specialty $specialty
 */
class RoomDailyStat extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_id',
        'specialty_id',
        'date',
        'atendidos_count',
        'ausentes_count',
        'llamados_count',
        'reintentos_count',
        'tiempo_espera_total_segundos',
        'tiempo_espera_muestras',
        'duracion_consulta_total_segundos',
        'duracion_consulta_muestras',
        'primer_llamado_at',
        'ultimo_atendido_at',
        'generado_at',
    ];

    protected $casts = [
        // Formato explícito: sin él, Eloquent serializa "date" con hora al
        // guardar, y updateOrCreate() ya no reconoce la fila existente al
        // buscar por la fecha "pura" que entrega toDateString().
        'date' => 'date:Y-m-d',
        'primer_llamado_at' => 'datetime',
        'ultimo_atendido_at' => 'datetime',
        'generado_at' => 'datetime',
    ];

    /** @return BelongsTo<Room, $this> */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /** @return BelongsTo<Specialty, $this> */
    public function specialty(): BelongsTo
    {
        return $this->belongsTo(Specialty::class);
    }
}
