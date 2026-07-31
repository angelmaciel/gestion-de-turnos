<?php

namespace App\Http\Resources;

use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Turno para pantallas autenticadas (preconsulta y panel del profesional).
 *
 * Los datos clínicos solo se incluyen para quien está autorizado a verlos:
 * Mesa de Entrada registra el turno pero no tiene por qué leer peso, altura
 * ni presión.
 *
 * @mixin Appointment
 */
class AppointmentResource extends JsonResource
{
    /**
     * mergeWhen introduce claves numéricas temporales que Laravel resuelve al
     * serializar, de ahí que el tipo de clave sea int|string y no solo string.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        $puedeVerClinico = $request->user()?->hasAnyRole(['preconsulta', 'profesional', 'admin']) ?? false;

        return [
            // Identificador para la API: opaco, no enumerable.
            'id' => $this->ulid,
            // Número que ve y escucha el paciente: correlativo del día.
            'turno' => $this->daily_number,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'attempts' => $this->attempts,

            'specialty_id' => $this->specialty_id,
            'room_id' => $this->room_id,
            'professional_id' => $this->professional_id,

            'patient' => new PatientResource($this->whenLoaded('patient')),
            'specialty' => new SpecialtyResource($this->whenLoaded('specialty')),
            'room' => $this->whenLoaded('room', fn () => ['id' => $this->room?->id, 'name' => $this->room?->name]),

            // Signos vitales e IMC: solo para roles clínicos.
            $this->mergeWhen($puedeVerClinico, fn () => [
                'weight' => $this->weight,
                'height' => $this->height,
                'blood_pressure' => $this->blood_pressure,
                'imc' => $this->imc,
                'imc_categoria' => $this->imc_categoria,
            ]),

            'registered_at' => $this->registered_at?->toIso8601String(),
            'preconsulta_at' => $this->preconsulta_at?->toIso8601String(),
            'called_at' => $this->called_at?->toIso8601String(),
            'attended_at' => $this->attended_at?->toIso8601String(),
            'last_called_at' => $this->last_called_at?->toIso8601String(),
        ];
    }
}
