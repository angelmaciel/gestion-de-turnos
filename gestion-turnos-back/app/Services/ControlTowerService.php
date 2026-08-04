<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Enums\RoomStatus;
use App\Events\ColaActualizada;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Professional;
use App\Models\Room;
use App\Support\RoomStatusSnapshot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Deriva el estado en vivo de cada sala para la Torre de Control del admin.
 *
 * El estado no se guarda en ninguna tabla: se calcula en cada consulta a
 * partir de los turnos del día, igual que hacen el resto de las pantallas.
 * Solo "en limpieza" es manual porque no hay forma de derivarlo de los datos.
 */
class ControlTowerService
{
    /** @return Collection<int, RoomStatusSnapshot> */
    public function snapshot(): Collection
    {
        return Room::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Room $room) => $this->estadoDeSala($room));
    }

    public function estadoDeSala(Room $room, ?Carbon $ahora = null): RoomStatusSnapshot
    {
        $ahora ??= now();

        // Se busca por professionals.room_id (siempre se setea al crear un
        // Professional) en vez de rooms.professional_id: esa columna es un
        // duplicado que solo mantiene sincronizado quien recuerde hacerlo.
        $professional = Professional::query()
            ->with(['specialty', 'user'])
            ->where('room_id', $room->id)
            ->first();

        if ($room->en_limpieza) {
            $minutos = $room->en_limpieza_desde ? (int) $room->en_limpieza_desde->diffInMinutes($ahora) : null;

            return new RoomStatusSnapshot($room, RoomStatus::LIMPIEZA, $minutos, null, $professional);
        }

        if (! $professional) {
            return new RoomStatusSnapshot($room, RoomStatus::SIN_PROFESIONAL, null, null, null);
        }

        $specialtyId = $professional->specialty_id;
        $umbral = (int) config('control_tower.retraso_critico_minutos');

        // 1) ¿Hay un turno llamado ahora mismo, atendiéndose en la sala?
        $llamado = Appointment::query()
            ->where('specialty_id', $specialtyId)
            ->where('status', AppointmentStatus::LLAMADO)
            ->whereNotNull('called_at')
            ->orderByDesc('called_at')
            ->first();

        if ($llamado) {
            $minutos = (int) $llamado->called_at->diffInMinutes($ahora);
            $status = $minutos > $umbral ? RoomStatus::RETRASO_CRITICO : RoomStatus::ATENDIENDO_A_TIEMPO;

            return new RoomStatusSnapshot($room, $status, $minutos, $llamado, $professional);
        }

        // 2) Nadie llamado: ¿hay alguien esperando en cola sin que lo llamen?
        $enCola = Appointment::query()
            ->where('specialty_id', $specialtyId)
            ->where('status', AppointmentStatus::PRECONSULTA_COMPLETA)
            ->whereNotNull('preconsulta_at')
            ->orderBy('preconsulta_at')
            ->first();

        if ($enCola) {
            $minutos = (int) $enCola->preconsulta_at->diffInMinutes($ahora);
            $status = $minutos > $umbral ? RoomStatus::RETRASO_CRITICO : RoomStatus::ATENDIENDO_A_TIEMPO;

            return new RoomStatusSnapshot($room, $status, $minutos, $enCola, $professional);
        }

        // 3) Ni llamado ni cola: ¿el último turno cerrado hoy fue ausente?
        $ultimoCerrado = Appointment::query()
            ->where('specialty_id', $specialtyId)
            ->whereIn('status', [AppointmentStatus::ATENDIDO, AppointmentStatus::AUSENTE])
            ->whereDate('daily_date', today())
            ->orderByDesc('last_called_at')
            ->first();

        if ($ultimoCerrado?->status === AppointmentStatus::AUSENTE) {
            return new RoomStatusSnapshot($room, RoomStatus::VACIA_POR_INASISTENCIA, null, $ultimoCerrado, $professional);
        }

        return new RoomStatusSnapshot($room, RoomStatus::LIBRE, null, null, $professional);
    }

    public function alternarLimpieza(Room $room, bool $activar): Room
    {
        $room->update([
            'en_limpieza' => $activar,
            'en_limpieza_desde' => $activar ? now() : null,
        ]);

        AuditLog::registrar($activar ? 'activo_limpieza' : 'desactivo_limpieza', null, ['sala' => $room->name]);

        broadcast(new ColaActualizada('limpieza'));

        return $room;
    }
}
