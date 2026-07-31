<?php

namespace App\Enums;

enum AppointmentStatus: string
{
    case REGISTRADO = 'registrado';
    case PRECONSULTA_COMPLETA = 'preconsulta_completa';
    case LLAMADO = 'llamado';
    case ATENDIDO = 'atendido';
    case AUSENTE = 'ausente';

    public function label(): string
    {
        return match ($this) {
            self::REGISTRADO => 'Registrado',
            self::PRECONSULTA_COMPLETA => 'Preconsulta completa',
            self::LLAMADO => 'Llamado',
            self::ATENDIDO => 'Atendido',
            self::AUSENTE => 'Ausente',
        };
    }
}
