<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAppointmentRequest extends FormRequest
{
    /*
      La cédula identifica al paciente en el historial, así que se guarda en un
      solo formato: dígitos pelados. Admitir "1.234.567" y "1234567" a la vez
      parte al mismo paciente en dos fichas y deja una auditoría que no cierra.
    */
    private const PATRON_CEDULA = '/^\d+$/';

    /*
      Letras, espacios, apóstrofos y guiones: alcanza para "D'Angelo" o
      "García-Ruiz" sin dejar pasar dígitos ni signos que solo entran por error
      de tipeo. \p{L} cubre acentos y ñ, y \p{M} las tildes que llegan como
      caracter combinante desde algunos teclados.
    */
    private const PATRON_NOMBRE = '/^[\p{L}\p{M}\s\'’\-]+$/u';

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
        $dni = $this->input('patient_dni', $this->input('cedula'));
        $nombre = $this->input('patient_name', $this->input('nombre'));

        $this->merge([
            'patient_dni' => is_string($dni) ? trim($dni) : $dni,
            // Espacios de más al costado o en el medio hacen que dos altas del
            // mismo paciente no coincidan al buscarlo. Se normaliza acá, antes
            // de validar, y no después de guardar.
            'patient_name' => is_string($nombre)
                ? preg_replace('/\s+/u', ' ', trim($nombre))
                : $nombre,
        ]);
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'patient_dni' => ['required', 'string', 'regex:'.self::PATRON_CEDULA, 'max:20'],
            'patient_name' => ['required', 'string', 'regex:'.self::PATRON_NOMBRE, 'max:100'],
            'specialty_id' => ['required', 'integer', 'exists:specialties,id'],
            'professional_id' => ['nullable', 'integer', 'exists:professionals,id'],
        ];
    }

    /**
     * El mensaje genérico de `regex` ("el formato es inválido") no dice qué
     * corregir, y este es el texto que mesa de entrada lee bajo el campo.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'patient_dni.regex' => 'La cédula solo admite números, sin puntos, espacios ni letras.',
            'patient_name.regex' => 'El nombre solo admite letras, espacios, apóstrofos y guiones.',
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
