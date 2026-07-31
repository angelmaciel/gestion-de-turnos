<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Señal de "algo cambió en las colas": la disparan Mesa de Entrada,
 * Preconsulta y el panel del profesional.
 *
 * A propósito NO lleva datos del paciente. El canal es público, así que solo
 * avisa que hay que recargar; cada pantalla vuelve a pedir su propia lista al
 * endpoint autenticado, que ya filtra por rol y especialidad. Así el aviso en
 * vivo no puede filtrar información clínica.
 */
class ColaActualizada implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public string $origen = '') {}

    public function broadcastOn(): array
    {
        return [
            new Channel('cola-turnos'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'cola.actualizada';
    }

    public function broadcastWith(): array
    {
        return [
            'origen' => $this->origen,
            'at' => now()->toIso8601String(),
        ];
    }
}
