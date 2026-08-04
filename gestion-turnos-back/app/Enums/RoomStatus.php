<?php

namespace App\Enums;

enum RoomStatus: string
{
    case ATENDIENDO_A_TIEMPO = 'atendiendo_a_tiempo';
    case RETRASO_CRITICO = 'retraso_critico';
    case LIMPIEZA = 'en_limpieza';
    case VACIA_POR_INASISTENCIA = 'vacia_por_inasistencia';
    case LIBRE = 'libre';
    case SIN_PROFESIONAL = 'sin_profesional';

    public function label(): string
    {
        return match ($this) {
            self::ATENDIENDO_A_TIEMPO => 'Atendiendo a tiempo',
            self::RETRASO_CRITICO => 'Retraso crítico',
            self::LIMPIEZA => 'En limpieza',
            self::VACIA_POR_INASISTENCIA => 'Vacía por inasistencia',
            self::LIBRE => 'Libre',
            self::SIN_PROFESIONAL => 'Sin profesional asignado',
        };
    }
}
