<?php

namespace Database\Seeders;

use App\Models\Professional;
use App\Models\Room;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProfessionalSeeder extends Seeder
{
    /**
     * Crea el perfil profesional de los usuarios con rol 'profesional'.
     * Sin esto, /api/profesional/* responde 403 aunque el rol esté asignado.
     *
     * Hay uno por cada especialidad a propósito: si una especialidad se queda
     * sin profesional, los turnos de esa especialidad pasan la preconsulta y
     * caen en una cola que nadie ve nunca.
     */
    public function run(): void
    {
        $profiles = [
            [
                'email' => 'profesional@example.com',
                'name' => 'Dr. Martín Sosa',
                'specialty' => 'Medicina General',
                'room' => 'Sala 1',
            ],
            [
                'email' => 'pediatra@example.com',
                'name' => 'Dra. Elena Paz',
                'specialty' => 'Pediatría',
                'room' => 'Sala 2',
            ],
            [
                'email' => 'odontologo@example.com',
                'name' => 'Dr. Hugo Benítez',
                'specialty' => 'Odontología',
                'room' => 'Sala 3',
            ],
            [
                'email' => 'ginecologa@example.com',
                'name' => 'Dra. Carla Duarte',
                'specialty' => 'Ginecología',
                'room' => 'Sala 4',
            ],
            [
                'email' => 'cardiologo@example.com',
                'name' => 'Dr. Raúl Ferreira',
                'specialty' => 'Cardiología',
                'room' => 'Sala 5',
            ],
            [
                'email' => 'traumatologa@example.com',
                'name' => 'Dra. Nadia Rojas',
                'specialty' => 'Traumatología',
                'room' => 'Sala 6',
            ],
        ];

        foreach ($profiles as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => bcrypt((string) config('seeding.password')),
                    'email_verified_at' => now(),
                ]
            );

            // UserSeeder ya pudo crear este usuario con un nombre genérico;
            // el nombre del médico se muestra en la TV, así que se impone acá.
            $user->update(['name' => $data['name']]);
            $user->syncRoles('profesional');

            $specialty = Specialty::where('name', $data['specialty'])->firstOrFail();
            $room = Room::where('name', $data['room'])->firstOrFail();

            $professional = Professional::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'specialty_id' => $specialty->id,
                    'room_id' => $room->id,
                ]
            );

            // rooms.professional_id duplica professionals.room_id; se mantienen
            // sincronizados para que ambos lados del esquema sean coherentes.
            $room->update(['professional_id' => $professional->id]);
        }
    }
}
