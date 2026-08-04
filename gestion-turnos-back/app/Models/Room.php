<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property int|null $professional_id
 * @property bool $en_limpieza
 * @property Carbon|null $en_limpieza_desde
 * @property-read Professional|null $professional
 */
class Room extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'professional_id',
        'en_limpieza',
        'en_limpieza_desde',
    ];

    protected $casts = [
        'en_limpieza' => 'boolean',
        'en_limpieza_desde' => 'datetime',
    ];

    /** @return BelongsTo<Professional, $this> */
    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }

    /** @return HasMany<RoomDailyStat, $this> */
    public function dailyStats(): HasMany
    {
        return $this->hasMany(RoomDailyStat::class);
    }
}
