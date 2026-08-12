<?php

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
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

it('expone el ULID como identificador y nunca el autoincremental', function () {
    $appointment = turnoEn(AppointmentStatus::REGISTRADO);

    $payload = $this->actingAs(usuarioCon('preconsulta'), 'sanctum')
        ->getJson('/api/preconsulta/turnos')
        ->assertOk()
        ->json('data.0');

    expect($payload['id'])->toBe($appointment->ulid)
        ->and($payload['id'])->not->toBe($appointment->id)
        ->and(strlen((string) $payload['id']))->toBe(26)
        ->and($payload['patient']['id'])->toBe($appointment->patient->ulid);
});

it('no permite enumerar turnos por el id autoincremental', function () {
    $appointment = turnoEn(AppointmentStatus::PRECONSULTA_COMPLETA);
    $professional = profesionalDe();

    // El id existe en la base, pero como identificador de ruta no resuelve.
    $this->actingAs($professional, 'sanctum')
        ->postJson("/api/profesional/turnos/{$appointment->id}/call")
        ->assertNotFound();

    // Con el ULID sí.
    $this->actingAs($professional, 'sanctum')
        ->postJson("/api/profesional/turnos/{$appointment->ulid}/call")
        ->assertOk();
});

it('numera los turnos de forma correlativa por día', function () {
    $entrada = usuarioCon('mesa de entrada');

    // Nombres sin dígitos: el alta los rechaza, porque un número dentro del
    // nombre ensucia la búsqueda del paciente en el historial.
    $nombres = ['Paciente Uno', 'Paciente Dos', 'Paciente Tres'];

    $numeros = collect(range(1, 3))->map(function (int $i) use ($entrada, $nombres) {
        return $this->actingAs($entrada, 'sanctum')
            ->postJson('/api/entrada/turnos', [
                'patient_dni' => "1000{$i}",
                'patient_name' => $nombres[$i - 1],
                'specialty_id' => $this->specialty->id,
            ])
            ->assertCreated()
            ->json('data.turno');
    });

    expect($numeros->all())->toBe([1, 2, 3]);
});

it('reinicia la numeración al cambiar el día', function () {
    // Turno de ayer con número alto: no debe influir en el de hoy.
    Appointment::create([
        'patient_id' => turnoEn(AppointmentStatus::ATENDIDO)->patient_id,
        'specialty_id' => $this->specialty->id,
        'status' => AppointmentStatus::ATENDIDO,
        'registered_at' => now()->subDay(),
        'daily_date' => today()->subDay(),
        'daily_number' => 87,
    ]);

    $numero = $this->actingAs(usuarioCon('mesa de entrada'), 'sanctum')
        ->postJson('/api/entrada/turnos', [
            'patient_dni' => '55667788',
            'patient_name' => 'Paciente de hoy',
            'specialty_id' => $this->specialty->id,
        ])
        ->assertCreated()
        ->json('data.turno');

    // turnoEn() ya consumió el 1 de hoy, así que este es el 2.
    expect($numero)->toBe(2);
});

it('la pantalla pública muestra el número del día, no el ULID', function () {
    $appointment = turnoEn(AppointmentStatus::PRECONSULTA_COMPLETA);

    $this->actingAs(profesionalDe(), 'sanctum')
        ->postJson("/api/profesional/turnos/{$appointment->ulid}/call")
        ->assertOk();

    $llamado = $this->getJson('/api/publico/llamados')->assertOk()->json('data.0');

    // El número cantado por altavoz tiene que ser corto y pronunciable.
    expect($llamado['turno'])->toBe($appointment->daily_number)
        ->and($llamado['turno'])->toBeInt()
        ->and($llamado['appointment_id'])->toBe($appointment->ulid);
});
