<?php

namespace App\Http\Resources;

use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Nunca expone cedula_hash: es un detalle interno del índice ciego y
 * publicarlo permitiría confirmar si una cédula concreta existe en el sistema.
 *
 * @mixin Patient
 */
class PatientResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'nombre' => $this->nombre,
            // La cédula solo para quien la necesita para identificar al paciente.
            'cedula' => $this->when(
                $request->user()?->hasAnyRole(['mesa de entrada', 'preconsulta', 'profesional', 'admin']) ?? false,
                fn () => $this->cedula
            ),
        ];
    }
}
