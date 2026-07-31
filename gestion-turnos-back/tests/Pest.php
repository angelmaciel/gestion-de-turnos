<?php

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Professional;
use App\Models\Room;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Caso base
|--------------------------------------------------------------------------
|
| Los tests de Feature levantan la aplicación y usan RefreshDatabase, que
| corre sobre SQLite en memoria (ver phpunit.xml). Los de Unit no necesitan
| ni aplicación ni base de datos.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Helpers compartidos
|--------------------------------------------------------------------------
|
| Viven acá y no en un archivo de test concreto para que cualquier archivo
| pueda usarlos aunque se ejecute aislado con --filter.
|
*/

/** Usuario con un rol concreto y sin perfil profesional. */
function usuarioCon(string $role): User
{
    $user = User::create([
        'name' => ucfirst($role),
        'email' => str_replace(' ', '', $role).'-'.uniqid().'@test.com',
        'password' => bcrypt('password123'),
    ]);

    $user->syncRoles($role);

    return $user;
}

/** Especialidad por defecto del escenario de pruebas. */
function especialidadPorDefecto(): Specialty
{
    return Specialty::firstOrCreate(['name' => 'Medicina General']);
}

/** Sala por defecto del escenario de pruebas. */
function salaPorDefecto(): Room
{
    return Room::firstOrCreate(['name' => 'Sala 1']);
}

/** Profesional con perfil, especialidad y sala. */
function profesionalDe(?Specialty $specialty = null, ?Room $room = null): User
{
    $user = usuarioCon('profesional');

    Professional::create([
        'user_id' => $user->id,
        'specialty_id' => ($specialty ?? especialidadPorDefecto())->id,
        'room_id' => ($room ?? salaPorDefecto())->id,
    ]);

    return $user;
}

/** Turno en un estado dado, con paciente propio para no chocar la cédula única. */
function turnoEn(AppointmentStatus $status, ?Specialty $specialty = null): Appointment
{
    $patient = Patient::create([
        'cedula' => (string) random_int(1000000, 9999999),
        'nombre' => 'Paciente Test',
    ]);

    return Appointment::create([
        'patient_id' => $patient->id,
        'specialty_id' => ($specialty ?? especialidadPorDefecto())->id,
        'status' => $status,
        'registered_at' => now(),
        'preconsulta_at' => $status === AppointmentStatus::REGISTRADO ? null : now(),
        'daily_date' => today(),
        'daily_number' => ((int) Appointment::whereDate('daily_date', today())->max('daily_number')) + 1,
    ]);
}
