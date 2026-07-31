<?php

namespace App\Http\Controllers\Api;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\PublicCallResource;
use App\Models\Appointment;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PublicScreenController extends Controller
{
    /**
     * Últimos llamados, para que la pantalla de sala de espera recupere su
     * estado al recargarse (el WebSocket solo trae eventos nuevos).
     *
     * Endpoint público: el recorte de campos lo hace PublicCallResource.
     */
    public function llamados(): AnonymousResourceCollection
    {
        $appointments = Appointment::query()
            ->whereIn('status', [AppointmentStatus::LLAMADO, AppointmentStatus::ATENDIDO])
            ->whereNotNull('last_called_at')
            ->whereDate('last_called_at', today())
            ->with(['patient', 'room', 'professional.user'])
            ->orderByDesc('last_called_at')
            ->limit(5)
            ->get();

        return PublicCallResource::collection($appointments);
    }
}
