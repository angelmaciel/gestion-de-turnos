<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas web
|--------------------------------------------------------------------------
|
| El frontend es un SPA aparte: acá solo viven un par de endpoints de
| diagnóstico que no exponen ningún dato.
|
| Se eliminaron las rutas /test/appointments, /test/patients y
| /test/professionals: volcaban la base entera SIN autenticación, con las
| cédulas y los datos clínicos ya descifrados por Eloquent. Cualquiera con
| acceso al puerto podía llevarse el historial completo de pacientes.
|
*/

Route::get('/', function () {
    return response()->json([
        'message' => 'API de gestión de turnos lista',
        'login' => '/api/auth/login',
        'health' => '/up',
    ]);
});

Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});

/**
 * Laravel redirige acá a los invitados cuando una petición no autenticada no
 * declara Accept: application/json. Sin esta ruta con nombre, el intento de
 * redirección lanza RouteNotFoundException y devuelve un 500 con traza.
 */
Route::get('/login', function () {
    return response()->json(['message' => 'No autenticado.'], 401);
})->name('login');
