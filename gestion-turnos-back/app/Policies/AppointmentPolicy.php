<?php

namespace App\Policies;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\User;

/**
 * Única fuente de verdad sobre quién puede tocar un turno.
 *
 * Regla central: un profesional solo opera turnos de SU especialidad y que no
 * hayan sido tomados por un colega. Un cardiólogo nunca ve ni llama pacientes
 * de neurología, aunque llame directamente a la API.
 */
class AppointmentPolicy
{
    /** Ver la ficha del turno, incluidos datos clínicos. */
    public function view(User $user, Appointment $appointment): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if ($user->hasRole('preconsulta')) {
            return true;
        }

        return $this->esSuTurno($user, $appointment);
    }

    /** Cargar peso, altura y presión. */
    public function completePreconsulta(User $user, Appointment $appointment): bool
    {
        if (! $user->hasAnyRole(['preconsulta', 'admin'])) {
            return false;
        }

        // Solo un turno que aún no pasó por preconsulta.
        return $appointment->status === AppointmentStatus::REGISTRADO;
    }

    /** Llamar o re-llamar al paciente al consultorio. */
    public function call(User $user, Appointment $appointment): bool
    {
        return $this->esSuTurno($user, $appointment)
            && in_array($appointment->status, [
                AppointmentStatus::PRECONSULTA_COMPLETA,
                AppointmentStatus::LLAMADO,
            ], true);
    }

    /** Cerrar la consulta como atendida. */
    public function attend(User $user, Appointment $appointment): bool
    {
        return $this->esSuTurno($user, $appointment)
            && $appointment->status === AppointmentStatus::LLAMADO;
    }

    /** Marcar que el paciente no se presentó. */
    public function absent(User $user, Appointment $appointment): bool
    {
        return $this->esSuTurno($user, $appointment)
            && $appointment->status === AppointmentStatus::LLAMADO;
    }

    /**
     * Pertenencia: mismo perfil profesional, misma especialidad y turno libre
     * o ya asignado a este profesional.
     */
    private function esSuTurno(User $user, Appointment $appointment): bool
    {
        $professional = $user->professional;

        if (! $professional) {
            return false;
        }

        if ($appointment->specialty_id !== $professional->specialty_id) {
            return false;
        }

        return $appointment->professional_id === null
            || $appointment->professional_id === $professional->id;
    }
}
