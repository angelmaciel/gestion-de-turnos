<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSpecialtyRequest;
use App\Http\Resources\SpecialtyResource;
use App\Models\Specialty;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SpecialtyController extends Controller
{
    /** Alimenta los desplegables de Mesa de Entrada. */
    public function index(): AnonymousResourceCollection
    {
        return SpecialtyResource::collection(
            Specialty::query()->orderBy('name')->get()
        );
    }

    /** Solo admin: la autorización vive en el Form Request. */
    public function store(StoreSpecialtyRequest $request): JsonResponse
    {
        $specialty = Specialty::create($request->validated());

        return (new SpecialtyResource($specialty))
            ->additional(['message' => 'Especialidad creada con éxito'])
            ->response()
            ->setStatusCode(201);
    }
}
