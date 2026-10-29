<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Límites de peticiones por minuto
    |--------------------------------------------------------------------------
    |
    | Los valores por defecto son los de producción. Solo las pruebas de carga
    | (qa/jmeter) los suben, porque todos sus usuarios virtuales salen de una
    | misma IP y chocarían contra el límite en segundos.
    |
    */

    // Por correo + IP: frena la fuerza bruta contra una cuenta.
    'login_por_correo' => (int) env('RATE_LIMIT_LOGIN', 5),

    // Por IP: frena a quien prueba muchas cuentas desde el mismo origen.
    'login_por_ip' => (int) env('RATE_LIMIT_LOGIN_IP', 20),

    // Por usuario autenticado (o IP): techo general de la API.
    'api' => (int) env('RATE_LIMIT_API', 120),

    // Por IP: pantalla pública de la sala de espera.
    'publico' => (int) env('RATE_LIMIT_PUBLICO', 30),

];
