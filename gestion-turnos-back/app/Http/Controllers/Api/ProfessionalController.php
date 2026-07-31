<?php

namespace App\Http\Controllers\Api;

use App\Enums\AppointmentStatus;
use App\Events\ColaActualizada;
use App\Http\Controllers\Controller;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Professional;
use App\Services\AppointmentCallService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ProfessionalController extends Controller
{
    public function __construct(private readonly AppointmentCallService $callService) {}

    /**
     * Cola de pacientes que ya pasaron por preconsulta y esperan
     * ser llamados por este profesional.
     */
    public function queue(Request $request): AnonymousResourceCollection
    {
        $professional = $this->currentProfessional($request);

        // Filtro por pertenencia en la consulta: nunca se cargan turnos de
        // otra especialidad para descartarlos después.
        $appointments = Appointment::query()
            ->where('status', AppointmentStatus::PRECONSULTA_COMPLETA)
            ->where('specialty_id', $professional->specialty_id)
            ->where(function ($query) use ($professional) {
                $query->whereNull('professional_id')
                    ->orWhere('professional_id', $professional->id);
            })
            ->with(['patient', 'specialty'])
            ->orderBy('preconsulta_at')
            ->orderBy('id')
            ->get();

        return AppointmentResource::collection($appointments);
    }

    /**
     * Llama (o re-llama) a un paciente al consultorio y lo anuncia
     * en la pantalla de la sala de espera vía WebSocket.
     */
    public function call(Request $request, Appointment $appointment): JsonResource
    {
        $this->authorize('call', $appointment);

        $appointment = $this->callService->llamar($appointment, $this->currentProfessional($request));

        return (new AppointmentResource($appointment))
            ->additional(['message' => 'Paciente llamado al consultorio']);
    }

    /** Finaliza la consulta. */
    public function attend(Request $request, Appointment $appointment): JsonResource
    {
        $this->authorize('attend', $appointment);

        $appointment->update([
            'status' => AppointmentStatus::ATENDIDO,
            'attended_at' => now(),
        ]);

        // Acceso a la ficha clínica completa del paciente durante la consulta.
        AuditLog::registrar('atendio_paciente', $appointment);

        broadcast(new ColaActualizada('atendido'));

        return (new AppointmentResource($appointment->load(['patient', 'specialty'])))
            ->additional(['message' => 'Atención finalizada']);
    }

    /** Marca al paciente como ausente. */
    public function absent(Request $request, Appointment $appointment): JsonResource
    {
        $this->authorize('absent', $appointment);

        $appointment->update(['status' => AppointmentStatus::AUSENTE]);

        AuditLog::registrar('marco_ausente', $appointment, ['intentos' => $appointment->attempts]);

        broadcast(new ColaActualizada('ausente'));

        return (new AppointmentResource($appointment->load(['patient', 'specialty'])))
            ->additional(['message' => 'Turno marcado como ausente']);
    }

    /** Perfil profesional del usuario autenticado. */
    private function currentProfessional(Request $request): Professional
    {
        $professional = $request->user()?->professional;

        if (! $professional) {
            throw new HttpException(403, 'Tu usuario no tiene un perfil de profesional configurado.');
        }

        return $professional;
    }
}
