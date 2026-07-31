<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['mesa de entrada', 'admin']) ?? false;
    }

    /**
     * El frontend histórico manda 'cedula'/'nombre'; se normaliza antes de
     * validar para no arrastrar ese mapeo dentro del controlador.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'patient_dni' => $this->input('patient_dni', $this->input('cedula')),
            'patient_name' => $this->input('patient_name', $this->input('nombre')),
        ]);
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'patient_dni' => ['required', 'string', 'max:20'],
            'patient_name' => ['required', 'string', 'max:100'],
            'specialty_id' => ['required', 'integer', 'exists:specialties,id'],
            'professional_id' => ['nullable', 'integer', 'exists:professionals,id'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'patient_dni' => 'cédula',
            'patient_name' => 'nombre',
            'specialty_id' => 'especialidad',
            'professional_id' => 'profesional',
        ];
    }
}
