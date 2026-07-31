<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Events\ColaActualizada;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Patient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Alta de un turno desde Mesa de Entrada.
 *
 * El número correlativo del día es un recurso en disputa: dos recepcionistas
 * registrando a la vez calcularían el mismo "siguiente número" y dos pacientes
 * terminarían con el mismo turno cantado por altavoz.
 *
 * Se protege por partida doble: Cache::lock serializa el cálculo, y el índice
 * único (daily_date, daily_number) actúa de red por si el lock expira o el
 * despliegue corre en varios nodos sin caché compartida.
 */
class AppointmentRegistrationService
{
    private const ESPERA_LOCK_SEGUNDOS = 5;

    private const DURACION_LOCK_SEGUNDOS = 10;

    /** @param  array{patient_dni: string, patient_name: string, specialty_id: int, professional_id?: int|null}  $datos */
    public function registrar(array $datos): Appointment
    {
        // La cédula está cifrada: la búsqueda va por índice ciego, no por WHERE.
        $patient = Patient::conCedula($datos['patient_dni'])->first()
            ?? Patient::create([
                'cedula' => $datos['patient_dni'],
                'nombre' => $datos['patient_name'],
            ]);

        $hoy = Carbon::today();
        $lock = Cache::lock("turnos:correlativo:{$hoy->toDateString()}", self::DURACION_LOCK_SEGUNDOS);

        $appointment = $lock->block(self::ESPERA_LOCK_SEGUNDOS, function () use ($datos, $patient, $hoy) {
            return DB::transaction(function () use ($datos, $patient, $hoy) {
                return Appointment::create([
                    'patient_id' => $patient->id,
                    'specialty_id' => $datos['specialty_id'],
                    'professional_id' => $datos['professional_id'] ?? null,
                    'status' => AppointmentStatus::REGISTRADO,
                    'registered_at' => now(),
                    'daily_date' => $hoy,
                    'daily_number' => $this->siguienteNumeroDelDia($hoy),
                ]);
            });
        });

        AuditLog::registrar('registro_turno', $appointment);

        // Nuevo turno esperando preconsulta: avisar a la pantalla de triaje.
        broadcast(new ColaActualizada('entrada'));

        return $appointment->load(['patient', 'specialty', 'professional']);
    }

    /** Correlativo que reinicia cada día. Se llama siempre dentro del lock. */
    private function siguienteNumeroDelDia(Carbon $dia): int
    {
        // whereDate y no where(): según el driver, la columna puede guardarse
        // como fecha o como datetime completo, y la comparación literal falla.
        $ultimo = Appointment::query()
            ->whereDate('daily_date', $dia)
            ->lockForUpdate()
            ->max('daily_number');

        return ((int) $ultimo) + 1;
    }
}
