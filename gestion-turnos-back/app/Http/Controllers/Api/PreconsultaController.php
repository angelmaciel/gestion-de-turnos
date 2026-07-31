<?php

namespace App\Http\Controllers\Api;

use App\Enums\AppointmentStatus;
use App\Events\ColaActualizada;
use App\Http\Controllers\Controller;
use App\Http\Requests\CompletePreconsultaRequest;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class PreconsultaController extends Controller
{
    /**
     * Turnos esperando preconsulta. Admite ?cedula= para el buscador.
     *
     * La cédula está cifrada en la base, así que no admite búsqueda parcial:
     * se resuelve por índice ciego y requiere el número completo. El nombre
     * sigue en claro (se muestra en la TV de todos modos) y sí acepta
     * coincidencia parcial, que es lo que hace útil al buscador.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $busqueda = trim((string) $request->query('cedula', ''));

        $appointments = Appointment::query()
            ->where('status', AppointmentStatus::REGISTRADO)
            ->when($busqueda !== '', function ($query) use ($busqueda) {
                $query->whereHas('patient', function ($q) use ($busqueda) {
                    $q->where('cedula_hash', Patient::hashCedula($busqueda))
                        ->orWhere('nombre', 'like', "%{$busqueda}%");
                });
            })
            ->with(['patient', 'specialty'])
            ->orderBy('registered_at')
            ->orderBy('id')
            ->get();

        return AppointmentResource::collection($appointments);
    }

    /**
     * Carga los signos vitales y deja el turno listo para el profesional.
     * La autorización sobre el turno concreto la resuelve AppointmentPolicy
     * a través del Form Request.
     */
    public function complete(CompletePreconsultaRequest $request, Appointment $appointment): JsonResource
    {
        $data = $request->validated();

        $appointment->update([
            'weight' => $data['weight'] ?? null,
            'height' => $data['height'] ?? null,
            'blood_pressure' => $data['blood_pressure'] ?? null,
            'status' => AppointmentStatus::PRECONSULTA_COMPLETA,
            'preconsulta_at' => now(),
        ]);

        // Se cargaron datos clínicos: queda registrado quién y cuándo.
        AuditLog::registrar('completo_preconsulta', $appointment, [
            'campos' => array_keys(array_filter($data, fn ($v) => $v !== null)),
        ]);

        // El paciente entra en la cola del profesional: avisar en vivo.
        broadcast(new ColaActualizada('preconsulta'));

        return (new AppointmentResource($appointment->load(['patient', 'specialty'])))
            ->additional(['message' => 'Preconsulta completada. El paciente ya figura en el panel del profesional.']);
    }
}
