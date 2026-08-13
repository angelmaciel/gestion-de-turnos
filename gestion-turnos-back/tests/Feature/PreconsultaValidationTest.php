<?php

use App\Enums\AppointmentStatus;
use Spatie\Permission\Models\Role;

/*
  La presión arterial se guarda en la historia clínica y se lee después para
  decidir. Antes era un string libre de hasta 20 caracteres, asi que "xx"
  entraba como signo vital y no habia forma de distinguir un error de tipeo
  de una medición real.
*/

beforeEach(function () {
    foreach (['mesa de entrada', 'preconsulta', 'profesional', 'admin'] as $role) {
        Role::findOrCreate($role, 'web');
        Role::findOrCreate($role, 'sanctum');
    }

    $this->preconsulta = usuarioCon('preconsulta');
});

/** Completa la preconsulta de un turno recién registrado. */
function completarCon(array $datos)
{
    $turno = turnoEn(AppointmentStatus::REGISTRADO);

    // Por ULID: el identificador público es opaco y la ruta nunca resuelve
    // por el autoincremental (ver PublicIdentifierTest).
    return test()->actingAs(test()->preconsulta, 'sanctum')
        ->postJson("/api/preconsulta/turnos/{$turno->ulid}/complete", array_merge([
            'weight' => 72.5,
            'height' => 170,
            'blood_pressure' => '120/80',
        ], $datos));
}

it('rechaza una presión que no tenga forma de sistólica/diastólica', function (string $presion) {
    completarCon(['blood_pressure' => $presion])
        ->assertStatus(422)
        ->assertJsonValidationErrors('blood_pressure');
})->with([
    'letras' => 'xx',
    'un solo número' => '120',
    'con texto' => '120/80 mmHg',
    'separador equivocado' => '120-80',
    'con decimales' => '120.5/80',
    'de cuatro cifras' => '1200/80',
    'vacía entre barras' => '/80',
]);

it('acepta las formas habituales de escribirla', function (string $presion) {
    completarCon(['blood_pressure' => $presion])->assertOk();
})->with([
    'normal' => '120/80',
    'baja' => '90/60',
    'alta' => '180/110',
    'de dos cifras' => '95/65',
]);

it('rechaza valores con formato correcto pero fuera de lo posible', function (string $presion) {
    completarCon(['blood_pressure' => $presion])
        ->assertStatus(422)
        ->assertJsonValidationErrors('blood_pressure');
})->with([
    'sistólica absurda' => '999/80',
    'sistólica muy baja' => '10/80',
    'diastólica absurda' => '120/999',
    'invertidas' => '80/120',
    'iguales' => '100/100',
]);

it('sigue aceptando que no se cargue la presión', function () {
    completarCon(['blood_pressure' => null])->assertOk();
});

it('explica cómo se escribe en vez de decir "formato inválido"', function () {
    completarCon(['blood_pressure' => 'xx'])
        ->assertStatus(422)
        ->assertJsonPath(
            'errors.blood_pressure.0',
            'Escribí la presión como sistólica/diastólica, por ejemplo 120/80.'
        );
});
