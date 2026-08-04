<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Umbral de retraso crítico
    |--------------------------------------------------------------------------
    |
    | Minutos desde que se llamó (o desde que un turno espera en cola) a
    | partir de los cuales la Torre de Control marca la sala en retraso
    | crítico. Todavía no hay suficiente historial en room_daily_stats para
    | calcular un promedio real por especialidad, así que arranca fijo; se
    | puede afinar por especialidad cuando haya semanas de datos.
    |
    */

    'retraso_critico_minutos' => (int) env('CONTROL_TOWER_RETRASO_MINUTOS', 20),

];
