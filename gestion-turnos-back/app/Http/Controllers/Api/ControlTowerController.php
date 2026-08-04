<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ToggleLimpiezaRequest;
use App\Http\Resources\RoomStatusResource;
use App\Models\Room;
use App\Services\ControlTowerService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class ControlTowerController extends Controller
{
    public function __construct(private readonly ControlTowerService $service) {}

    /** Estado en vivo de las 6 salas para el tablero del admin. */
    public function index(): AnonymousResourceCollection
    {
        return RoomStatusResource::collection($this->service->snapshot());
    }

    /** Marca (o libera) una sala en modo limpieza. */
    public function toggleLimpieza(ToggleLimpiezaRequest $request, Room $room): JsonResource
    {
        $room = $this->service->alternarLimpieza($room, $request->boolean('activar'));

        return (new RoomStatusResource($this->service->estadoDeSala($room)))
            ->additional(['message' => $room->en_limpieza ? 'Sala marcada en limpieza' : 'Sala reactivada']);
    }
}
