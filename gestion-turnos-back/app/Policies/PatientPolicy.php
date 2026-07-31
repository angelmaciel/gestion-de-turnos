<?php

namespace App\Policies;

use App\Models\Patient;
use App\Models\User;

/**
 * El paciente solo es visible para quien participa de su atención:
 * mesa de entrada, preconsulta, o un profesional que tenga un turno suyo
 * en su propia especialidad.
 */
class PatientPolicy
{
    public function view(User $user, Patient $patient): bool
    {
        if ($user->hasAnyRole(['admin', 'preconsulta', 'mesa de entrada'])) {
            return true;
        }

        $professional = $user->professional;

        if (! $professional) {
            return false;
        }

        // Filtra por pertenencia: no basta con tener rol de profesional.
        return $patient->appointments()
            ->where('specialty_id', $professional->specialty_id)
            ->where(function ($query) use ($professional) {
                $query->whereNull('professional_id')
                    ->orWhere('professional_id', $professional->id);
            })
            ->exists();
    }
}
