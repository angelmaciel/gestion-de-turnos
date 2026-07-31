<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $ulid
 * @property string $cedula
 * @property string|null $cedula_hash
 * @property string $nombre
 * @property-read Collection<int, Appointment> $appointments
 */
class Patient extends Model
{
    use HasFactory;

    protected $fillable = [
        'cedula',
        'nombre',
    ];

    protected $casts = [
        // Cifrada en reposo: en la base no queda el número legible.
        'cedula' => 'encrypted',
    ];

    /** El hash es interno; nunca debe salir en una respuesta de la API. */
    protected $hidden = [
        'cedula_hash',
    ];

    protected static function booted(): void
    {
        static::creating(function (Patient $patient) {
            $patient->ulid ??= (string) Str::ulid();
        });

        // El índice ciego se mantiene solo: cualquier alta o cambio de cédula
        // recalcula el hash sin que el resto del código tenga que acordarse.
        static::saving(function (Patient $patient) {
            if ($patient->isDirty('cedula')) {
                $patient->cedula_hash = static::hashCedula($patient->cedula);
            }
        });
    }

    /** Identificador público opaco: nunca se expone el autoincremental. */
    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * Índice ciego: HMAC determinista de la cédula normalizada.
     *
     * Es determinista a propósito (permite buscar) pero no reversible: quien
     * lea la base no puede obtener la cédula desde el hash. Usa la APP_KEY como
     * clave, así que sin ella tampoco se puede recalcular por fuerza bruta.
     */
    public static function hashCedula(?string $cedula): ?string
    {
        if ($cedula === null || $cedula === '') {
            return null;
        }

        // Normalizar evita que "1.234.567" y "1234567" se traten como distintas.
        $normalizada = preg_replace('/[^0-9A-Za-z]/', '', $cedula);

        return hash_hmac('sha256', mb_strtolower($normalizada), config('app.key'));
    }

    /**
     * Búsqueda exacta por cédula, sin descifrar toda la tabla.
     *
     * @param  Builder<Patient>  $query
     * @return Builder<Patient>
     */
    public function scopeConCedula(Builder $query, string $cedula): Builder
    {
        return $query->where('cedula_hash', static::hashCedula($cedula));
    }

    /** @return HasMany<Appointment, $this> */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}
