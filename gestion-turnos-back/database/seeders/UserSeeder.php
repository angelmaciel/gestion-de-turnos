<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Estos usuarios tienen contraseña conocida y emails predecibles:
        // en producción serían una puerta abierta.
        if (app()->isProduction()) {
            $this->command?->error('UserSeeder no corre en producción: crearía cuentas con contraseña conocida.');

            return;
        }

        $users = [
            ['name' => 'Admin', 'email' => 'admin@example.com', 'role' => 'admin'],
            ['name' => 'Mesa de Entrada', 'email' => 'entrada@example.com', 'role' => 'mesa de entrada'],
            ['name' => 'Preconsulta', 'email' => 'preconsulta@example.com', 'role' => 'preconsulta'],
            ['name' => 'Profesional', 'email' => 'profesional@example.com', 'role' => 'profesional'],
        ];

        foreach ($users as $userData) {
            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => bcrypt((string) config('seeding.password')),
                    'email_verified_at' => now(),
                ]
            );

            $user->syncRoles($userData['role']);
        }
    }
}
