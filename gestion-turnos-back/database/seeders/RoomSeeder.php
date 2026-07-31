<?php

namespace Database\Seeders;

use App\Models\Room;
use Illuminate\Database\Seeder;

class RoomSeeder extends Seeder
{
    public function run(): void
    {
        // Una sala por especialidad: ver ProfessionalSeeder.
        foreach (range(1, 6) as $numero) {
            Room::firstOrCreate(['name' => "Sala {$numero}"]);
        }
    }
}
