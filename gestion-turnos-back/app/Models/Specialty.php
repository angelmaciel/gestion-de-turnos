<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property-read Collection<int, Professional> $professionals
 * @property-read Collection<int, Appointment> $appointments
 */
class Specialty extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    /** @return HasMany<Professional, $this> */
    public function professionals(): HasMany
    {
        return $this->hasMany(Professional::class);
    }

    /** @return HasMany<Appointment, $this> */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}
