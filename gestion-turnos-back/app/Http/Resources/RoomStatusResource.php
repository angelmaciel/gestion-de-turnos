<?php

namespace App\Http\Resources;

use App\Support\RoomStatusSnapshot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Estado en vivo de una sala para la Torre de Control del admin.
 *
 * @mixin RoomStatusSnapshot
 */
class RoomStatusResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $room = $this->room;
        $professional = $this->professional;
        $turno = $this->turnoRelevante;

        return [
            'room_id' => $room->id,
            'room_name' => $room->name,
            'specialty' => $professional?->specialty?->name,
            'professional' => $professional?->user?->name,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'en_limpieza' => $room->en_limpieza,
            'en_limpieza_desde' => $room->en_limpieza_desde?->toIso8601String(),
            'minutos_en_estado' => $this->minutosEnEstado,
            'turno_relevante' => $this->when($turno !== null, fn () => [
                'turno' => $turno->daily_number,
                'paciente' => $turno->patient?->nombre,
                'estado' => $turno->status->value,
            ]),
        ];
    }
}
