<?php

use App\Enums\AppointmentStatus;
use App\Events\PacienteLlamado;
use App\Models\Room;
use App\Models\Specialty;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['mesa de entrada', 'preconsulta', 'profesional', 'admin'] as $role) {
        Role::findOrCreate($role, 'web');
        Role::findOrCreate($role, 'sanctum');
    }

    $this->specialty = Specialty::create(['name' => 'Medicina General']);
    $this->room = Room::create(['name' => 'Sala 1']);
});

/** Campos que la TV puede mostrar. Cualquier agregado debe ser deliberado. */
const CAMPOS_PUBLICOS = ['appointment_id', 'turno', 'paciente', 'sala', 'profesional', 'llamado_at'];

/** Nada de esto puede salir por un canal que ve toda la sala de espera. */
const CAMPOS_PROHIBIDOS = ['cedula', 'especialidad', 'specialty', 'weight', 'height', 'blood_pressure', 'imc', 'attempts'];

it('no requiere autenticación', function () {
    $this->getJson('/api/publico/llamados')->assertOk();
});

it('no expone datos sensibles en el endpoint público', function () {
    $appointment = turnoEn(AppointmentStatus::PRECONSULTA_COMPLETA);
    $appointment->update(['weight' => 80, 'height' => 175, 'blood_pressure' => '130/85']);

    $this->actingAs(profesionalDe(), 'sanctum')
        ->postJson("/api/profesional/turnos/{$appointment->ulid}/call")
        ->assertOk();

    $payload = $this->getJson('/api/publico/llamados')->assertOk()->json('data');

    expect($payload)->toHaveCount(1)
        ->and(array_keys($payload[0]))->toBe(CAMPOS_PUBLICOS);

    $crudo = json_encode($payload);
    foreach (CAMPOS_PROHIBIDOS as $prohibido) {
        expect($crudo)->not->toContain($prohibido);
    }

    expect($crudo)->not->toContain($appointment->patient->cedula);
});

it('no expone datos sensibles en el evento de broadcast', function () {
    $appointment = turnoEn(AppointmentStatus::PRECONSULTA_COMPLETA);
    $appointment->update(['weight' => 80, 'height' => 175]);
    $appointment->refresh();

    $datos = (new PacienteLlamado($appointment))->broadcastWith();

    expect(array_keys($datos))->toBe(CAMPOS_PUBLICOS);

    foreach (CAMPOS_PROHIBIDOS as $prohibido) {
        expect($datos)->not->toHaveKey($prohibido);
    }
});
