<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $ulid
 * @property int|null $daily_number
 * @property Carbon|null $daily_date
 * @property int $patient_id
 * @property int $specialty_id
 * @property int|null $room_id
 * @property int|null $professional_id
 * @property AppointmentStatus $status
 * @property string|null $weight
 * @property string|null $height
 * @property string|null $blood_pressure
 * @property int $attempts
 * @property Carbon|null $registered_at
 * @property Carbon|null $preconsulta_at
 * @property Carbon|null $called_at
 * @property Carbon|null $attended_at
 * @property Carbon|null $last_called_at
 * @property-read float|null $imc
 * @property-read string|null $imc_categoria
 * @property-read Patient|null $patient
 * @property-read Specialty|null $specialty
 * @property-read Room|null $room
 * @property-read Professional|null $professional
 */
class Appointment extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'specialty_id',
        'room_id',
        'professional_id',
        'status',
        'weight',
        'height',
        'blood_pressure',
        'attempts',
        'last_called_at',
        'registered_at',
        'preconsulta_at',
        'called_at',
        'attended_at',
        'daily_number',
        'daily_date',
    ];

    protected $casts = [
        // Datos clínicos cifrados en reposo. Se puede sin costo porque ninguna
        // consulta filtra ni ordena por ellos: solo se leen y escriben por
        // registro. El IMC se calcula en PHP, después de descifrar.
        'weight' => 'encrypted',
        'height' => 'encrypted',
        'blood_pressure' => 'encrypted',
        'status' => AppointmentStatus::class,
        'registered_at' => 'datetime',
        'preconsulta_at' => 'datetime',
        'called_at' => 'datetime',
        'attended_at' => 'datetime',
        'last_called_at' => 'datetime',
        'daily_date' => 'date',
    ];

    protected $attributes = [
        'attempts' => 0,
        'status' => AppointmentStatus::REGISTRADO,
    ];

    protected static function booted(): void
    {
        static::creating(function (Appointment $appointment) {
            $appointment->ulid ??= (string) Str::ulid();
        });
    }

    /**
     * Las rutas resuelven por ULID, nunca por el ID autoincremental: así la API
     * no permite enumerar turnos ajenos probando /turnos/1, /turnos/2...
     */
    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /** Se derivan de peso + altura, así que viajan siempre en el JSON. */
    protected $appends = ['imc', 'imc_categoria'];

    /**
     * Índice de masa corporal: peso(kg) / altura(m)².
     * Null si falta alguno de los dos datos.
     */
    protected function imc(): Attribute
    {
        return Attribute::get(function (): ?float {
            // Vienen como string porque están cifrados en reposo.
            $peso = (float) $this->weight;
            $alturaCm = (float) $this->height;

            if ($peso <= 0.0 || $alturaCm <= 0.0) {
                return null;
            }

            $metros = $alturaCm / 100;

            return round($peso / ($metros ** 2), 1);
        });
    }

    /** Categoría OMS del IMC, para mostrar junto al número. */
    protected function imcCategoria(): Attribute
    {
        return Attribute::get(function (): ?string {
            $imc = $this->imc;

            return match (true) {
                $imc === null => null,
                $imc < 18.5 => 'Bajo peso',
                $imc < 25 => 'Normal',
                $imc < 30 => 'Sobrepeso',
                default => 'Obesidad',
            };
        });
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /** @return BelongsTo<Specialty, $this> */
    public function specialty(): BelongsTo
    {
        return $this->belongsTo(Specialty::class);
    }

    /** @return BelongsTo<Room, $this> */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /** @return BelongsTo<Professional, $this> */
    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }
}
