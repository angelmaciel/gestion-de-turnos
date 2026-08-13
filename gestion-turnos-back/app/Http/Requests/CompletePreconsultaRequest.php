<?php

namespace App\Http\Requests;

use App\Models\Appointment;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class CompletePreconsultaRequest extends FormRequest
{
    /*
      Sistólica/diastólica, como se escribe en la ficha: "120/80".

      Antes la presión era un string libre de hasta 20 caracteres, asi que
      "xx" entraba y quedaba guardado como signo vital. En una historia
      clínica eso es peor que una cédula mal escrita: el dato se lee después
      para decidir, y no hay forma de saber si fue un error de tipeo o una
      medición real.
    */
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
     * Rangos fisiológicos, aparte del formato.
     *
     * El patrón solo garantiza dos números de dos o tres cifras, así que
     * "999/999" lo pasaría. Va como regla propia y no como otro patrón porque
     * comparar números con una expresión regular es ilegible.
     */
    private function rangoDePresion(): Closure
    {
        return function (string $atributo, mixed $valor, Closure $fallar): void {
            // Si el formato ya falló, su mensaje es el que corresponde: sumar
            // otro solo apila ruido bajo el mismo campo.
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

            // La sistólica es la presión durante el latido y la diastólica la
            // del reposo entre latidos, así que la primera es siempre mayor.
            // Invertidas casi siempre son los dos números tipeados al revés.
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
     * El mensaje genérico de `regex` dice "el formato es inválido", que no
     * explica cómo se escribe. Este es el texto que preconsulta lee bajo el
     * campo mientras tiene al paciente enfrente.
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
