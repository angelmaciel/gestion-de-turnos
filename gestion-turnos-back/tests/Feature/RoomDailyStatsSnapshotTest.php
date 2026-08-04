<?php

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Room;
use App\Models\RoomDailyStat;
use App\Models\Specialty;
use App\Services\RoomDailyStatsService;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['mesa de entrada', 'preconsulta', 'profesional', 'admin'] as $role) {
        Role::findOrCreate($role, 'web');
        Role::findOrCreate($role, 'sanctum');
    }

    $this->specialty = Specialty::create(['name' => 'Medicina General']);
    $this->room = Room::create(['name' => 'Sala 1']);
    profesionalDe($this->specialty, $this->room);
});

/**
 * Turno con fecha y timestamps controlados a mano: turnoEn() (tests/Pest.php)
 * calcula daily_number contra el día de HOY, lo que choca con el índice único
 * (daily_date, daily_number) al mover turnos a un día distinto.
 */
function turnoDe(AppointmentStatus $status, Carbon\Carbon $fecha, Specialty $specialty, array $extra = []): Appointment
{
    static $contador = [];
    $clave = $fecha->toDateString();
    $contador[$clave] = ($contador[$clave] ?? 0) + 1;

    $patient = Patient::create([
        'cedula' => (string) random_int(1000000, 9999999),
        'nombre' => 'Paciente Test',
    ]);

    return Appointment::create(array_merge([
        'patient_id' => $patient->id,
        'specialty_id' => $specialty->id,
        'status' => $status,
        'daily_date' => $clave,
        'daily_number' => $contador[$clave],
    ], $extra));
}

it('genera el snapshot y es idempotente al recalcular', function () {
    $ayer = today()->subDay();

    turnoDe(AppointmentStatus::ATENDIDO, $ayer, $this->specialty, [
        'preconsulta_at' => $ayer->copy()->setTime(9, 0),
        'called_at' => $ayer->copy()->setTime(9, 10),
        'attended_at' => $ayer->copy()->setTime(9, 25),
        'attempts' => 1,
    ]);

    turnoDe(AppointmentStatus::AUSENTE, $ayer, $this->specialty, [
        'preconsulta_at' => $ayer->copy()->setTime(10, 0),
        'called_at' => $ayer->copy()->setTime(10, 5),
        'attempts' => 2,
    ]);

    app(RoomDailyStatsService::class)->generarParaFecha($ayer);

    expect(RoomDailyStat::count())->toBe(1);

    $stat = RoomDailyStat::first();
    expect($stat->room_id)->toBe($this->room->id)
        ->and($stat->atendidos_count)->toBe(1)
        ->and($stat->ausentes_count)->toBe(1)
        ->and($stat->llamados_count)->toBe(2)
        ->and($stat->reintentos_count)->toBe(1)
        ->and($stat->tiempo_espera_muestras)->toBe(2)
        ->and($stat->duracion_consulta_muestras)->toBe(1);

    // Recalcular el mismo día no debe duplicar la fila.
    app(RoomDailyStatsService::class)->generarParaFecha($ayer);
    expect(RoomDailyStat::count())->toBe(1);
});

it('respeta --from y --to para reprocesar varios dias', function () {
    $dia1 = today()->subDays(2);
    $dia2 = today()->subDay();

    turnoDe(AppointmentStatus::ATENDIDO, $dia1, $this->specialty, [
        'preconsulta_at' => $dia1->copy()->setTime(9, 0),
        'called_at' => $dia1->copy()->setTime(9, 10),
        'attended_at' => $dia1->copy()->setTime(9, 20),
    ]);

    turnoDe(AppointmentStatus::ATENDIDO, $dia2, $this->specialty, [
        'preconsulta_at' => $dia2->copy()->setTime(9, 0),
        'called_at' => $dia2->copy()->setTime(9, 10),
        'attended_at' => $dia2->copy()->setTime(9, 20),
    ]);

    $this->artisan('control-tower:snapshot-stats', [
        '--from' => $dia1->toDateString(),
        '--to' => $dia2->toDateString(),
    ])->assertSuccessful();

    expect(RoomDailyStat::count())->toBe(2);
    expect(RoomDailyStat::whereDate('date', $dia1->toDateString())->first()->atendidos_count)->toBe(1);
    expect(RoomDailyStat::whereDate('date', $dia2->toDateString())->first()->atendidos_count)->toBe(1);
});

it('no mezcla turnos de otro dia en el conteo', function () {
    $ayer = today()->subDay();
    $anteayer = today()->subDays(2);

    turnoDe(AppointmentStatus::ATENDIDO, $ayer, $this->specialty);
    turnoDe(AppointmentStatus::ATENDIDO, $anteayer, $this->specialty);
    turnoDe(AppointmentStatus::ATENDIDO, $anteayer, $this->specialty);

    app(RoomDailyStatsService::class)->generarParaFecha($ayer);

    $stat = RoomDailyStat::whereDate('date', $ayer->toDateString())->first();
    expect($stat->atendidos_count)->toBe(1);
});
