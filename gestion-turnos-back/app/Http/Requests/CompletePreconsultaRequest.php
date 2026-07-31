<?php

namespace App\Http\Requests;

use App\Models\Appointment;
use Illuminate\Foundation\Http\FormRequest;

class CompletePreconsultaRequest extends FormRequest
{
    /**
     * La autorización sobre el turno concreto la resuelve la Policy,
     * así queda en un solo lugar y se puede testear aparte.
     */
    public function authorize(): bool
    {
        $appointment = $this->route('appointment');

        return $appointment instanceof Appointment
            && $this->user()?->can('completePreconsulta', $appointment);
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'weight' => ['nullable', 'numeric', 'min:0.5', 'max:400'],
            // Altura en centímetros.
            'height' => ['nullable', 'numeric', 'min:30', 'max:250'],
            'blood_pressure' => ['nullable', 'string', 'max:20'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'weight' => 'peso',
            'height' => 'altura',
            'blood_pressure' => 'presión arterial',
        ];
    }
}
