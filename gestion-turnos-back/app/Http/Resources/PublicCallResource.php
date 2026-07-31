<?php

namespace App\Http\Resources;

use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Llamado tal como se muestra en la TV de la sala de espera.
 *
 * Este recurso lo consume un endpoint PÚBLICO y un canal WebSocket público:
 * lo ve cualquiera que esté en la sala. Solo lleva lo indispensable para que
 * el paciente sepa a dónde ir.
 *
 * Deliberadamente NO incluye: cédula, especialidad (permitiría inferir la
 * condición médica) ni datos clínicos. Hay tests que fallan si alguien
 * agrega uno de esos campos.
 *
 * @mixin Appointment
 */
class PublicCallResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'appointment_id' => $this->ulid,
            'turno' => $this->daily_number,
            'paciente' => $this->patient?->nombre,
            'sala' => $this->room?->name,
            'profesional' => $this->professional?->user?->name,
            'llamado_at' => $this->last_called_at?->toIso8601String(),
        ];
    }
}
