<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAppointmentRequest;
use App\Http\Resources\AppointmentResource;
use App\Services\AppointmentRegistrationService;
use Illuminate\Http\JsonResponse;

class EntranceController extends Controller
{
    public function __construct(private readonly AppointmentRegistrationService $registrationService) {}

    public function store(StoreAppointmentRequest $request): JsonResponse
    {
        /** @var array{patient_dni: string, patient_name: string, specialty_id: int, professional_id?: int|null} $data */
        $data = $request->validated();

        $appointment = $this->registrationService->registrar($data);

        return (new AppointmentResource($appointment))
            ->additional(['message' => 'Turno creado correctamente'])
            ->response()
            ->setStatusCode(201);
    }
}
