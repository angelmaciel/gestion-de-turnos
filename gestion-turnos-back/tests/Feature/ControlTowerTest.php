<?php

use App\Enums\AppointmentStatus;
use App\Events\ColaActualizada;
use App\Models\Room;
use App\Models\Specialty;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['mesa de entrada', 'preconsulta', 'profesional', 'admin'] as $role) {
        Role::findOrCreate($role, 'web');
        Role::findOrCreate($role, 'sanctum');
    }

    $this->specialty = Specialty::create(['name' => 'Medicina General']);
    $this->room = Room::create(['name' => 'Sala 1']);
    $this->umbral = (int) config('control_tower.retraso_critico_minutos');
});

it('niega el acceso a quien no es admin', function () {
    $this->actingAs(usuarioCon('preconsulta'), 'sanctum')
        ->getJson('/api/admin/torre-control')
        ->assertForbidden();
});

it('marca atendiendo a tiempo cuando el llamado es reciente', function () {
    profesionalDe();
    $appointment = turnoEn(AppointmentStatus::LLAMADO);
    $appointment->update(['called_at' => now()->subMinutes(2)]);

    $this->actingAs(usuarioCon('admin'), 'sanctum')
        ->getJson('/api/admin/torre-control')
        ->assertOk()
        ->assertJsonPath('data.0.room_name', 'Sala 1')
        ->assertJsonPath('data.0.status', 'atendiendo_a_tiempo');
});

it('marca retraso critico cuando el llamado supera el umbral', function () {
    profesionalDe();
    $appointment = turnoEn(AppointmentStatus::LLAMADO);
    $appointment->update(['called_at' => now()->subMinutes($this->umbral + 5)]);

    $this->actingAs(usuarioCon('admin'), 'sanctum')
        ->getJson('/api/admin/torre-control')
        ->assertOk()
        ->assertJsonPath('data.0.status', 'retraso_critico');
});

it('marca retraso critico por cola sin llamar aunque nadie este en sala', function () {
    profesionalDe();
    $appointment = turnoEn(AppointmentStatus::PRECONSULTA_COMPLETA);
    $appointment->update(['preconsulta_at' => now()->subMinutes($this->umbral + 5)]);

    $this->actingAs(usuarioCon('admin'), 'sanctum')
        ->getJson('/api/admin/torre-control')
        ->assertOk()
        ->assertJsonPath('data.0.status', 'retraso_critico');
});

it('marca vacia por inasistencia tras el ultimo ausente sin cola', function () {
    profesionalDe();
    $appointment = turnoEn(AppointmentStatus::AUSENTE);
    $appointment->update(['last_called_at' => now()->subMinutes(15)]);

    $this->actingAs(usuarioCon('admin'), 'sanctum')
        ->getJson('/api/admin/torre-control')
        ->assertOk()
        ->assertJsonPath('data.0.status', 'vacia_por_inasistencia');
});

it('la limpieza tiene prioridad sobre cualquier otro estado calculado', function () {
    profesionalDe();
    $appointment = turnoEn(AppointmentStatus::LLAMADO);
    $appointment->update(['called_at' => now()->subMinutes($this->umbral + 5)]);
    $this->room->update(['en_limpieza' => true, 'en_limpieza_desde' => now()]);

    $this->actingAs(usuarioCon('admin'), 'sanctum')
        ->getJson('/api/admin/torre-control')
        ->assertOk()
        ->assertJsonPath('data.0.status', 'en_limpieza');
});

it('el admin puede alternar el modo limpieza y dispara ColaActualizada', function () {
    Event::fake([ColaActualizada::class]);

    $this->actingAs(usuarioCon('admin'), 'sanctum')
        ->postJson("/api/admin/salas/{$this->room->id}/limpieza", ['activar' => true])
        ->assertOk()
        ->assertJsonPath('data.en_limpieza', true);

    Event::assertDispatched(ColaActualizada::class);
    $this->assertDatabaseHas('audit_logs', ['accion' => 'activo_limpieza']);
    $this->assertDatabaseHas('rooms', ['id' => $this->room->id, 'en_limpieza' => true]);
});

it('rechaza alternar limpieza sin rol admin', function () {
    $this->actingAs(profesionalDe(), 'sanctum')
        ->postJson("/api/admin/salas/{$this->room->id}/limpieza", ['activar' => true])
        ->assertForbidden();
});
