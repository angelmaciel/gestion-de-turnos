<?php

namespace App\Http\Requests;

use App\Models\Appointment;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class CompletePreconsultaRequest extends FormRequest
{
    /** Sistólica/diastólica, como se escribe en la ficha: "120/80". */
    private const PATRON_PRESION = '/^\d{2,3}\/\d{2,3}$/';

    /** Límites amplios a propósito: descartan el disparate, no el caso raro. */
    private const SISTOLICA_MIN = 60;

    private const SISTOLICA_MAX = 300;

    private const DIASTOLICA_MIN = 30;

    private const DIASTOLICA_MAX = 200;

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

    /** @return array<string, array<int, string|Closure>> */
    public function rules(): array
    {
        return [
            'weight' => ['nullable', 'numeric', 'min:0.5', 'max:400'],
            // Altura en centímetros.
            'height' => ['nullable', 'numeric', 'min:30', 'max:250'],
            'blood_pressure' => [
                'nullable',
                'string',
                'regex:'.self::PATRON_PRESION,
                $this->rangoDePresion(),
            ],
        ];
    }

    /**
     * Rangos fisiológicos, aparte del formato: el patrón solo garantiza dos
     * números de dos o tres cifras, así que "999/999" lo pasaría.
     */
    private function rangoDePresion(): Closure
    {
        return function (string $atributo, mixed $valor, Closure $fallar): void {
            // Si el formato ya falló, su mensaje es el que corresponde.
            if (! is_string($valor) || preg_match(self::PATRON_PRESION, $valor) !== 1) {
                return;
            }

            [$sistolica, $diastolica] = array_map('intval', explode('/', $valor));

            if ($sistolica < self::SISTOLICA_MIN || $sistolica > self::SISTOLICA_MAX) {
                $fallar('La sistólica debe estar entre '.self::SISTOLICA_MIN.' y '.self::SISTOLICA_MAX.'.');

                return;
            }

            if ($diastolica < self::DIASTOLICA_MIN || $diastolica > self::DIASTOLICA_MAX) {
                $fallar('La diastólica debe estar entre '.self::DIASTOLICA_MIN.' y '.self::DIASTOLICA_MAX.'.');

                return;
            }

            // La sistólica es siempre mayor; invertidas suelen ser un tipeo
            // al revés.
            if ($diastolica >= $sistolica) {
                $fallar('La sistólica tiene que ser mayor que la diastólica.');
            }
        };
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

    /**
     * El genérico de `regex` no explica cómo se escribe, y este es el texto
     * que se lee bajo el campo con el paciente enfrente.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'blood_pressure.regex' => 'Escribí la presión como sistólica/diastólica, por ejemplo 120/80.',
        ];
    }
}
