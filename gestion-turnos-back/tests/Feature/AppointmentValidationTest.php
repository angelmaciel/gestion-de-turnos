<?php

use App\Models\Patient;
use Spatie\Permission\Models\Role;

/*
  La cédula y el nombre son los datos con los que después se busca al paciente
  en el historial, así que entran en un solo formato. Las reglas viven en el
  backend y no solo en el formulario, porque el formulario se saltea con
  cualquier cliente HTTP.

  El índice ciego de Patient::hashCedula ya ignora puntos y guiones, así que
  esas variantes no duplicaban al paciente; las letras sí, porque hashean
  distinto. Aparte, sin regla el valor guardado quedaba como lo tipeó quien
  cargó primero y la misma cédula se mostraba de formas distintas.
*/

beforeEach(function () {
    foreach (['mesa de entrada', 'preconsulta', 'profesional', 'admin'] as $role) {
        Role::findOrCreate($role, 'web');
        Role::findOrCreate($role, 'sanctum');
    }

    $this->specialty = especialidadPorDefecto();
    $this->entrada = usuarioCon('mesa de entrada');
});

/** Alta de turno con los datos de paciente que se quieran probar. */
function altaCon(array $datos)
{
    return test()->actingAs(test()->entrada, 'sanctum')
        ->postJson('/api/entrada/turnos', array_merge([
            'patient_dni' => '4567890',
            'patient_name' => 'Ana Gómez',
            'specialty_id' => test()->specialty->id,
        ], $datos));
}

it('rechaza cédulas que no sean solo dígitos', function (string $cedula) {
    altaCon(['patient_dni' => $cedula])
        ->assertStatus(422)
        ->assertJsonValidationErrors('patient_dni');
})->with([
    'con letras' => 'A1234567',
    'con puntos' => '1.234.567',
    'con guion' => '1234-567',
    'con espacio interno' => '1234 567',
    'solo letras' => 'sin cedula',
    'vacía' => '',
]);

it('acepta una cédula de dígitos y le recorta los espacios de los bordes', function () {
    altaCon(['patient_dni' => '  7654321  '])->assertCreated();

    // La cédula está cifrada en reposo: se llega por el índice ciego, y lo que
    // se compara es el valor descifrado, que es el que después se muestra.
    expect(Patient::conCedula('7654321')->first()?->cedula)->toBe('7654321');
});

it('rechaza nombres con dígitos o signos raros', function (string $nombre) {
    altaCon(['patient_name' => $nombre])
        ->assertStatus(422)
        ->assertJsonValidationErrors('patient_name');
})->with([
    'con número' => 'Paciente 3',
    'con arroba' => 'ana@correo.com',
    'con paréntesis' => 'Ana Gómez (hija)',
    'con barra' => 'Ana/Gómez',
    'con punto' => 'Ana G. Gómez',
    'vacío' => '',
]);

it('acepta nombres con acentos, ñ, apóstrofos y guiones', function (string $nombre) {
    altaCon(['patient_name' => $nombre])->assertCreated();

    expect(Patient::where('nombre', $nombre)->exists())->toBeTrue();
})->with([
    'con acento' => 'Ramón Peña',
    'con apóstrofo' => "D'Angelo Núñez",
    'con guion' => 'García-Ruiz Ferreira',
]);

it('colapsa los espacios de más del nombre para que la búsqueda coincida', function () {
    altaCon(['patient_name' => '  Ana   María  Gómez '])->assertCreated();

    expect(Patient::where('nombre', 'Ana María Gómez')->exists())->toBeTrue();
});

it('devuelve un mensaje que dice qué corregir, no "formato inválido"', function () {
    altaCon(['patient_dni' => '1.234.567'])
        ->assertStatus(422)
        ->assertJsonPath('errors.patient_dni.0', 'La cédula solo admite números, sin puntos, espacios ni letras.');
});
