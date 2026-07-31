<?php

use App\Enums\AppointmentStatus;
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

it('impide que un profesional de otra especialidad vea el turno en su cola', function () {
    turnoEn(AppointmentStatus::PRECONSULTA_COMPLETA);

    $otraEspecialidad = Specialty::create(['name' => 'Cardiología']);
    $otraSala = Room::create(['name' => 'Sala 9']);

    $this->actingAs(profesionalDe($otraEspecialidad, $otraSala), 'sanctum')
        ->getJson('/api/profesional/cola')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('devuelve 403 si un profesional ajeno intenta llamar el turno', function () {
    $appointment = turnoEn(AppointmentStatus::PRECONSULTA_COMPLETA);

    $otraEspecialidad = Specialty::create(['name' => 'Cardiología']);
    $otraSala = Room::create(['name' => 'Sala 9']);

    $this->actingAs(profesionalDe($otraEspecialidad, $otraSala), 'sanctum')
        ->postJson("/api/profesional/turnos/{$appointment->ulid}/call")
        ->assertForbidden();
});

it('devuelve 403 si un colega intenta operar un turno ya tomado', function () {
    $appointment = turnoEn(AppointmentStatus::PRECONSULTA_COMPLETA);

    $dueno = profesionalDe();
    $this->actingAs($dueno, 'sanctum')
        ->postJson("/api/profesional/turnos/{$appointment->ulid}/call")
        ->assertOk();

    // Mismo especialidad, pero el turno ya tiene dueño.
    $colega = profesionalDe();

    $this->actingAs($colega, 'sanctum')
        ->postJson("/api/profesional/turnos/{$appointment->ulid}/attend")
        ->assertForbidden();

    $this->actingAs($colega, 'sanctum')
        ->postJson("/api/profesional/turnos/{$appointment->ulid}/absent")
        ->assertForbidden();
});

it('devuelve 403 a un usuario con rol profesional pero sin perfil', function () {
    // Tiene el rol pero no la fila en `professionals`: el bug que rompía el panel.
    $this->actingAs(usuarioCon('profesional'), 'sanctum')
        ->getJson('/api/profesional/cola')
        ->assertForbidden();
});

it('impide que un profesional registre turnos', function () {
    $this->actingAs(profesionalDe(), 'sanctum')
        ->postJson('/api/entrada/turnos', [
            'patient_dni' => '1111111',
            'patient_name' => 'Intruso',
            'specialty_id' => $this->specialty->id,
        ])
        ->assertForbidden();
});

it('impide que un no-admin cree especialidades', function () {
    $this->actingAs(usuarioCon('preconsulta'), 'sanctum')
        ->postJson('/api/especialidades', ['name' => 'Dermatología'])
        ->assertForbidden();

    $this->actingAs(usuarioCon('admin'), 'sanctum')
        ->postJson('/api/especialidades', ['name' => 'Dermatología'])
        ->assertCreated();
});

it('impide atender o marcar ausente un turno que no fue llamado', function () {
    $appointment = turnoEn(AppointmentStatus::PRECONSULTA_COMPLETA);
    $professional = profesionalDe();

    $this->actingAs($professional, 'sanctum')
        ->postJson("/api/profesional/turnos/{$appointment->ulid}/attend")
        ->assertForbidden();

    $this->actingAs($professional, 'sanctum')
        ->postJson("/api/profesional/turnos/{$appointment->ulid}/absent")
        ->assertForbidden();
});

it('impide completar dos veces la misma preconsulta', function () {
    $appointment = turnoEn(AppointmentStatus::PRECONSULTA_COMPLETA);

    $this->actingAs(usuarioCon('preconsulta'), 'sanctum')
        ->postJson("/api/preconsulta/turnos/{$appointment->ulid}/complete", [
            'weight' => 70,
            'height' => 170,
            'blood_pressure' => '120/80',
        ])
        ->assertForbidden();
});

it('impide que un profesional cargue la preconsulta', function () {
    $appointment = turnoEn(AppointmentStatus::REGISTRADO);

    $this->actingAs(profesionalDe(), 'sanctum')
        ->postJson("/api/preconsulta/turnos/{$appointment->ulid}/complete", [
            'weight' => 70,
            'height' => 170,
        ])
        ->assertForbidden();
});

it('exige autenticación en todos los endpoints de datos de pacientes', function () {
    $appointment = turnoEn(AppointmentStatus::PRECONSULTA_COMPLETA);

    $this->getJson('/api/preconsulta/turnos')->assertUnauthorized();
    $this->getJson('/api/profesional/cola')->assertUnauthorized();
    $this->postJson("/api/profesional/turnos/{$appointment->ulid}/call")->assertUnauthorized();
    $this->postJson('/api/entrada/turnos', [])->assertUnauthorized();
});
