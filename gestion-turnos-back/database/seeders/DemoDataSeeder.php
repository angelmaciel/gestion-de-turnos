<?php

namespace Database\Seeders;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Professional;
use App\Models\Specialty;
use Illuminate\Database\Seeder;

/**
 * Datos de prueba para que las cuatro pantallas abran con contenido real:
 * turnos esperando preconsulta, cola cargada para CADA profesional,
 * un llamado activo en la TV e historial de atendidos/ausentes.
 *
 * Es idempotente: si ya hay turnos cargados, no hace nada.
 */
class DemoDataSeeder extends Seeder
{
    private int $correlativo = 0;

    public function run(): void
    {
        if (Appointment::exists()) {
            $this->command?->warn('Ya hay turnos cargados: DemoDataSeeder no hizo nada.');

            return;
        }

        $esp = Specialty::pluck('id', 'name');
        $profDe = fn (string $nombre) => Professional::where('specialty_id', $esp[$nombre])->first();

        $p = collect([
            ['cedula' => '30125478', 'nombre' => 'María Fernanda Ríos'],
            ['cedula' => '28934112', 'nombre' => 'Carlos Alberto Núñez'],
            ['cedula' => '41288907', 'nombre' => 'Lucía Beltrán'],
            ['cedula' => '35774201', 'nombre' => 'Jorge Ramírez'],
            ['cedula' => '50112388', 'nombre' => 'Sofía Ledesma'],
            ['cedula' => '27455610', 'nombre' => 'Roberto Cabrera'],
            ['cedula' => '44320199', 'nombre' => 'Valentina Ortiz'],
            ['cedula' => '39877452', 'nombre' => 'Diego Sandoval'],
            ['cedula' => '52014466', 'nombre' => 'Camila Aguirre'],
            ['cedula' => '31600788', 'nombre' => 'Héctor Villalba'],
            ['cedula' => '46701255', 'nombre' => 'Brenda Cáceres'],
            ['cedula' => '29188340', 'nombre' => 'Ernesto Duarte'],
            ['cedula' => '53422109', 'nombre' => 'Tamara Giménez'],
            ['cedula' => '37009821', 'nombre' => 'Óscar Medina'],
            ['cedula' => '48335670', 'nombre' => 'Paola Insfrán'],
            ['cedula' => '26744019', 'nombre' => 'Ramón Escobar'],
            // La cédula está cifrada: la búsqueda va por índice ciego, no por WHERE.
        ])->map(fn ($d) => Patient::conCedula($d['cedula'])->first() ?? Patient::create($d));

        // --- Esperando preconsulta: llenan la pantalla de Triaje ---
        $this->make($p[0], 'Medicina General', AppointmentStatus::REGISTRADO, ['registered_at' => now()->subMinutes(14)]);
        $this->make($p[1], 'Pediatría', AppointmentStatus::REGISTRADO, ['registered_at' => now()->subMinutes(11)]);
        $this->make($p[2], 'Odontología', AppointmentStatus::REGISTRADO, ['registered_at' => now()->subMinutes(7)]);
        $this->make($p[10], 'Cardiología', AppointmentStatus::REGISTRADO, ['registered_at' => now()->subMinutes(4)]);
        $this->make($p[11], 'Ginecología', AppointmentStatus::REGISTRADO, ['registered_at' => now()->subMinutes(2)]);

        // --- Listos para ser llamados: uno o más por CADA profesional ---
        $this->enCola($p[3], 'Medicina General', 55, 81.40, 178.00, '130/85');
        $this->enCola($p[4], 'Medicina General', 48, 62.10, 165.00, '110/70');
        $this->enCola($p[5], 'Pediatría', 35, 24.60, 122.00, '95/60');
        $this->colaLargaMedicinaGeneral();
        $this->enCola($p[12], 'Odontología', 30, 58.00, 158.00, '112/70');
        $this->enCola($p[13], 'Cardiología', 26, 94.30, 172.00, '145/95');
        $this->enCola($p[14], 'Ginecología', 22, 66.80, 168.00, '118/76');
        $this->enCola($p[15], 'Traumatología', 18, 79.10, 181.00, '124/80');

        // --- Llamado activo: es lo que muestra la TV al cargar ---
        $general = $profDe('Medicina General');
        $this->make($p[6], 'Medicina General', AppointmentStatus::LLAMADO, [
            'registered_at' => now()->subMinutes(70),
            'preconsulta_at' => now()->subMinutes(60),
            'called_at' => now()->subMinutes(2),
            'last_called_at' => now()->subMinutes(2),
            'attempts' => 1,
            'weight' => 70.00,
            'height' => 171.00,
            'blood_pressure' => '120/80',
            'professional_id' => $general?->id,
            'room_id' => $general?->room_id,
        ]);

        // --- Historial del día ---
        $this->historial($p[7], 'Medicina General', 120, 88.20, 174.00, '140/90', 1, AppointmentStatus::ATENDIDO);
        $this->historial($p[8], 'Pediatría', 180, 18.90, 108.00, '90/55', 2, AppointmentStatus::ATENDIDO);
        $this->historial($p[9], 'Medicina General', 240, 75.50, 180.00, '125/82', 3, AppointmentStatus::AUSENTE);

        $this->command?->info('DemoDataSeeder: '.Patient::count().' pacientes y '.Appointment::count().' turnos cargados.');
    }

    /**
     * Cola cargada para Medicina General: sirve para probar el panel del médico
     * con volumen real (scroll, llamar a uno del medio, re-llamar, etc.).
     */
    private function colaLargaMedicinaGeneral(): void
    {
        $enEspera = [
            ['61200341', 'Alicia Vera',           72.30, 161.00, '128/84'],
            ['33871902', 'Fabián Ojeda',          95.60, 183.00, '138/88'],
            ['57094412', 'Rocío Maldonado',       54.90, 157.00, '105/68'],
            ['28615577', 'Gustavo Arce',          88.00, 176.00, '142/91'],
            ['49230866', 'Mariela Cañete',        61.20, 164.00, '117/74'],
            ['35908123', 'Néstor Bogado',         78.40, 173.00, '133/86'],
            ['52771004', 'Silvana Torales',       66.70, 169.00, '120/78'],
            ['41056398', 'Julián Acosta',         84.10, 180.00, '126/82'],
            ['30442719', 'Beatriz Samudio',       59.80, 155.00, '113/72'],
            ['46983250', 'Andrés Riveros',        91.20, 179.00, '147/93'],
        ];

        foreach ($enEspera as $i => [$cedula, $nombre, $peso, $altura, $presion]) {
            $patient = Patient::conCedula($cedula)->first()
                ?? Patient::create(['cedula' => $cedula, 'nombre' => $nombre]);

            // Escalonados para que el orden de la cola (por preconsulta_at) sea claro.
            $this->enCola($patient, 'Medicina General', 44 - ($i * 4), $peso, $altura, $presion);
        }
    }

    /** Turno que ya pasó preconsulta y espera en la cola del profesional. */
    private function enCola(Patient $patient, string $especialidad, int $haceMinutos, float $peso, float $altura, string $presion): void
    {
        $this->make($patient, $especialidad, AppointmentStatus::PRECONSULTA_COMPLETA, [
            'registered_at' => now()->subMinutes($haceMinutos + 15),
            'preconsulta_at' => now()->subMinutes($haceMinutos),
            'weight' => $peso,
            'height' => $altura,
            'blood_pressure' => $presion,
        ]);
    }

    /** Turno ya cerrado (atendido o ausente), con su profesional y sala. */
    private function historial(Patient $patient, string $especialidad, int $haceMinutos, float $peso, float $altura, string $presion, int $intentos, AppointmentStatus $estado): void
    {
        $esp = Specialty::where('name', $especialidad)->firstOrFail();
        $prof = Professional::where('specialty_id', $esp->id)->first();

        $this->make($patient, $especialidad, $estado, [
            'registered_at' => now()->subMinutes($haceMinutos + 40),
            'preconsulta_at' => now()->subMinutes($haceMinutos + 25),
            'called_at' => now()->subMinutes($haceMinutos + 10),
            'last_called_at' => now()->subMinutes($haceMinutos + 10),
            'attended_at' => $estado === AppointmentStatus::ATENDIDO ? now()->subMinutes($haceMinutos) : null,
            'attempts' => $intentos,
            'weight' => $peso,
            'height' => $altura,
            'blood_pressure' => $presion,
            'professional_id' => $prof?->id,
            'room_id' => $prof?->room_id,
        ]);
    }

    /** @param  array<string, mixed>  $extra */
    private function make(Patient $patient, string $especialidad, AppointmentStatus $status, array $extra = []): void
    {
        // El correlativo del día lo asigna el servicio en la app real; acá se
        // lleva a mano para no depender de locks al sembrar.
        $this->correlativo++;

        Appointment::create(array_merge([
            'patient_id' => $patient->id,
            'specialty_id' => Specialty::where('name', $especialidad)->firstOrFail()->id,
            'status' => $status,
            'daily_date' => today(),
            'daily_number' => $this->correlativo,
        ], $extra));
    }
}
