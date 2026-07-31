<?php

use App\Enums\AppointmentStatus;
use App\Events\PacienteLlamado;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Room;
use App\Models\Specialty;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['mesa de entrada', 'preconsulta', 'profesional', 'admin'] as $role) {
        Role::findOrCreate($role, 'web');
        Role::findOrCreate($role, 'sanctum');
    }

    $this->specialty = Specialty::create(['name' => 'Medicina General']);
    $this->room = Room::create(['name' => 'Sala 1']);
});

it('recorre el flujo completo de entrada a atendido', function () {
    Event::fake([PacienteLlamado::class]);

    // 1. Mesa de entrada registra el turno.
    $response = $this->actingAs(usuarioCon('mesa de entrada'), 'sanctum')
        ->postJson('/api/entrada/turnos', [
            'patient_dni' => '9998887',
            'patient_name' => 'Ana Gómez',
            'specialty_id' => $this->specialty->id,
        ]);

    $response->assertCreated()->assertJsonPath('data.status', 'registrado');
    $appointmentId = $response->json('data.id');

    // 2. Preconsulta carga signos vitales y el IMC se deriva solo.
    $this->actingAs(usuarioCon('preconsulta'), 'sanctum')
        ->postJson("/api/preconsulta/turnos/{$appointmentId}/complete", [
            'weight' => 72.5,
            'height' => 170,
            'blood_pressure' => '120/80',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'preconsulta_completa')
        ->assertJsonPath('data.imc', 25.1)
        ->assertJsonPath('data.imc_categoria', 'Sobrepeso');

    // 3. El profesional ve el turno en su cola y lo llama.
    $professional = profesionalDe();

    $this->actingAs($professional, 'sanctum')
        ->getJson('/api/profesional/cola')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $appointmentId);

    $this->actingAs($professional, 'sanctum')
        ->postJson("/api/profesional/turnos/{$appointmentId}/call")
        ->assertOk()
        ->assertJsonPath('data.status', 'llamado')
        ->assertJsonPath('data.attempts', 1)
        ->assertJsonPath('data.room_id', $this->room->id);

    Event::assertDispatched(PacienteLlamado::class);

    // 4. Se finaliza la atención.
    $this->actingAs($professional, 'sanctum')
        ->postJson("/api/profesional/turnos/{$appointmentId}/attend")
        ->assertOk()
        ->assertJsonPath('data.status', 'atendido');

    // $appointmentId es el ULID público, no la clave primaria.
    expect(Appointment::where('ulid', $appointmentId)->sole()->attended_at)->not->toBeNull();
});

it('incrementa los intentos al re-llamar', function () {
    Event::fake([PacienteLlamado::class]);

    $appointment = turnoEn(AppointmentStatus::PRECONSULTA_COMPLETA);
    $professional = profesionalDe();

    foreach ([1, 2, 3] as $esperado) {
        $this->actingAs($professional, 'sanctum')
            ->postJson("/api/profesional/turnos/{$appointment->ulid}/call")
            ->assertOk()
            ->assertJsonPath('data.attempts', $esperado);
    }

    Event::assertDispatchedTimes(PacienteLlamado::class, 3);
});

it('deriva el IMC a null cuando falta la altura', function () {
    $appointment = turnoEn(AppointmentStatus::REGISTRADO);

    $this->actingAs(usuarioCon('preconsulta'), 'sanctum')
        ->postJson("/api/preconsulta/turnos/{$appointment->ulid}/complete", [
            'weight' => 70,
            'blood_pressure' => '120/80',
        ])
        ->assertOk()
        ->assertJsonPath('data.imc', null)
        ->assertJsonPath('data.imc_categoria', null);
});

it('rechaza altura y peso fuera de rango', function () {
    $appointment = turnoEn(AppointmentStatus::REGISTRADO);

    $this->actingAs(usuarioCon('preconsulta'), 'sanctum')
        ->postJson("/api/preconsulta/turnos/{$appointment->ulid}/complete", [
            'weight' => 900,
            'height' => 12,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['weight', 'height']);
});

it('busca en preconsulta por cédula exacta y por nombre parcial', function () {
    $buscado = Patient::create(['cedula' => '30125478', 'nombre' => 'María Ríos']);
    $otro = Patient::create(['cedula' => '99887766', 'nombre' => 'Pedro Gómez']);

    foreach ([$buscado, $otro] as $patient) {
        Appointment::create([
            'patient_id' => $patient->id,
            'specialty_id' => $this->specialty->id,
            'status' => AppointmentStatus::REGISTRADO,
            'registered_at' => now(),
        ]);
    }

    $user = usuarioCon('preconsulta');

    // Por cédula COMPLETA: resuelve por índice ciego sobre el dato cifrado.
    $this->actingAs($user, 'sanctum')
        ->getJson('/api/preconsulta/turnos?cedula=30125478')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.patient.nombre', 'María Ríos');

    // La cédula se normaliza: con puntos encuentra lo mismo.
    $this->actingAs($user, 'sanctum')
        ->getJson('/api/preconsulta/turnos?cedula=30.125.478')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    // Una cédula parcial NO encuentra: es el costo aceptado del cifrado.
    $this->actingAs($user, 'sanctum')
        ->getJson('/api/preconsulta/turnos?cedula=301254')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    // Para búsquedas parciales está el nombre, que sigue en claro.
    $this->actingAs($user, 'sanctum')
        ->getJson('/api/preconsulta/turnos?cedula=Gómez')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.patient.cedula', '99887766');
});

it('cifra en la base los datos clínicos y la cédula', function () {
    $appointment = turnoEn(AppointmentStatus::REGISTRADO);

    $this->actingAs(usuarioCon('preconsulta'), 'sanctum')
        ->postJson("/api/preconsulta/turnos/{$appointment->ulid}/complete", [
            'weight' => 72.5,
            'height' => 170,
            'blood_pressure' => '120/80',
        ])->assertOk();

    // Lectura cruda, sin pasar por Eloquent: es lo que vería quien acceda
    // directamente a la base de datos o robe un backup.
    $crudo = DB::table('appointments')->where('id', $appointment->id)->first();

    expect((string) $crudo->weight)->not->toContain('72.5')
        ->and((string) $crudo->blood_pressure)->not->toContain('120/80')
        ->and((string) $crudo->weight)->toStartWith('eyJpdiI6');

    $paciente = DB::table('patients')->where('id', $appointment->patient_id)->first();
    expect((string) $paciente->cedula)->not->toContain($appointment->patient->cedula)
        ->and($paciente->cedula_hash)->not->toBeNull();

    // Pero la aplicación sigue leyendo los valores reales.
    $appointment->refresh();
    expect((float) $appointment->weight)->toBe(72.5)
        ->and($appointment->blood_pressure)->toBe('120/80')
        ->and($appointment->imc)->toBe(25.1);
});
