<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Events\ColaActualizada;
use App\Events\PacienteLlamado;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Professional;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Llamado de un paciente al consultorio.
 *
 * Vive en un servicio y no en el controlador porque son varios pasos que deben
 * ocurrir juntos: tomar el turno, registrar la auditoría y anunciarlo.
 *
 * Es el punto del sistema donde dos profesionales de la misma especialidad
 * compiten por el mismo recurso: ambos ven el turno libre en su cola y pueden
 * pulsar "Llamar" a la vez. Sin bloqueo, los dos se lo llevarían y el paciente
 * sería anunciado a dos consultorios distintos.
 */
class AppointmentCallService
{
    private const ESPERA_LOCK_SEGUNDOS = 5;

    private const DURACION_LOCK_SEGUNDOS = 10;

    public function llamar(Appointment $appointment, Professional $professional): Appointment
    {
        $lock = Cache::lock("turno:{$appointment->id}:llamado", self::DURACION_LOCK_SEGUNDOS);

        // block() espera a que se libere en vez de fallar de inmediato: si el
        // colega apretó medio segundo antes, este llamado no se pierde, se
        // evalúa después contra el estado ya actualizado.
        return $lock->block(self::ESPERA_LOCK_SEGUNDOS, function () use ($appointment, $professional) {
            return DB::transaction(function () use ($appointment, $professional) {
                // Relectura dentro del lock: el estado pudo cambiar mientras se esperaba.
                $appointment->refresh();

                $this->asegurarQueSigueDisponible($appointment, $professional);

                $appointment->forceFill([
                    'professional_id' => $professional->id,
                    'room_id' => $professional->room_id,
                    'status' => AppointmentStatus::LLAMADO,
                    'attempts' => $appointment->attempts + 1,
                    'called_at' => $appointment->called_at ?? now(),
                    'last_called_at' => now(),
                ])->save();

                $appointment->load(['patient', 'specialty', 'room', 'professional.user']);

                AuditLog::registrar('llamo_paciente', $appointment, ['intento' => $appointment->attempts]);

                broadcast(new PacienteLlamado($appointment));
                broadcast(new ColaActualizada('llamado'));

                return $appointment;
            });
        });
    }

    /**
     * Revalida dentro del lock. La Policy ya autorizó antes, pero entre esa
     * comprobación y este punto otro profesional pudo tomar el turno.
     */
    private function asegurarQueSigueDisponible(Appointment $appointment, Professional $professional): void
    {
        if ($appointment->professional_id !== null && $appointment->professional_id !== $professional->id) {
            throw new HttpException(409, 'Otro profesional acaba de tomar este turno.');
        }

        if (! in_array($appointment->status, [
            AppointmentStatus::PRECONSULTA_COMPLETA,
            AppointmentStatus::LLAMADO,
        ], true)) {
            throw new HttpException(422, "El turno no está disponible para ser llamado (estado actual: {$appointment->status->label()}).");
        }
    }
}
