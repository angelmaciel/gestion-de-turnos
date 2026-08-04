<?php

namespace App\Support;

use App\Enums\RoomStatus;
use App\Models\Appointment;
use App\Models\Professional;
use App\Models\Room;

/** Resultado de evaluar el estado en vivo de una sala para la Torre de Control. */
final readonly class RoomStatusSnapshot
{
    public function __construct(
        public Room $room,
        public RoomStatus $status,
        public ?int $minutosEnEstado,
        public ?Appointment $turnoRelevante,
        public ?Professional $professional,
    ) {}
}
