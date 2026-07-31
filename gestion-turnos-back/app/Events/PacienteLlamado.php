<?php

namespace App\Events;

use App\Models\Appointment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Anuncia en la pantalla de sala de espera que un paciente fue llamado.
 *
 * Se emite de forma síncrona (ShouldBroadcastNow) porque el llamado debe
 * aparecer en la TV al instante y el stack no corre un queue worker.
 */
class PacienteLlamado implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Appointment $appointment) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('turnos-publico'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'paciente.llamado';
    }

    /**
     * Este canal es público: lo ve cualquiera en la sala de espera.
     * Solo viaja lo mínimo para que el paciente sepa a dónde ir.
     *
     * Deliberadamente NO se envían: cédula, especialidad (deja inferir la
     * condición médica), ni datos clínicos (peso, altura, presión, IMC).
     */
    public function broadcastWith(): array
    {
        $this->appointment->loadMissing(['patient', 'room', 'professional.user']);

        return [
            'appointment_id' => $this->appointment->ulid,
            'turno' => $this->appointment->daily_number,
            'paciente' => $this->appointment->patient?->nombre,
            'sala' => $this->appointment->room?->name,
            'profesional' => $this->appointment->professional?->user?->name,
            'llamado_at' => $this->appointment->last_called_at?->toIso8601String(),
        ];
    }
}
