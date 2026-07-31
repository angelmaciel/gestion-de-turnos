<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'user_email', 'user_roles', 'accion',
        'appointment_id', 'patient_id', 'ip', 'user_agent', 'contexto',
    ];

    protected $casts = [
        'contexto' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Deja constancia de un acceso o cambio sobre datos de un paciente.
     *
     * Nunca debe romper la operación clínica: si la auditoría falla, se
     * registra el problema en el log y la petición sigue su curso.
     */
    public static function registrar(string $accion, ?Appointment $appointment = null, array $contexto = []): void
    {
        try {
            $user = Auth::user();

            static::create([
                'user_id' => $user?->id,
                // Se copian email y roles porque el usuario puede cambiar o borrarse
                // después, y el registro tiene que seguir siendo interpretable.
                'user_email' => $user?->email,
                'user_roles' => $user?->getRoleNames()->implode(','),
                'accion' => $accion,
                'appointment_id' => $appointment?->id,
                'patient_id' => $appointment?->patient_id,
                'ip' => Request::ip(),
                'user_agent' => substr((string) Request::userAgent(), 0, 255),
                'contexto' => $contexto ?: null,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }
}
