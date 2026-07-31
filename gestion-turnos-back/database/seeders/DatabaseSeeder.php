<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            SpecialtySeeder::class,
            RoomSeeder::class,
            UserSeeder::class,
            ProfessionalSeeder::class,
            DemoDataSeeder::class,
        ]);
    }
}
