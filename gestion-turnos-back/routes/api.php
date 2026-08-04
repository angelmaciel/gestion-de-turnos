<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ControlTowerController;
use App\Http\Controllers\Api\EntranceController;
use App\Http\Controllers\Api\PreconsultaController;
use App\Http\Controllers\Api\ProfessionalController;
use App\Http\Controllers\Api\PublicScreenController;
use App\Http\Controllers\Api\SpecialtyController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    // throttle:login frena la fuerza bruta (ver AppServiceProvider).
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    // El SPA lo consulta al cargar para saber quién está autenticado, en vez
    // de guardar los datos del usuario en localStorage.
    Route::get('me', [AuthController::class, 'me'])->middleware('auth:sanctum');
});

// 📺 Pantalla de sala de espera: pública, igual que la vista /tv del frontend.
Route::get('publico/llamados', [PublicScreenController::class, 'llamados'])
    ->middleware('throttle:publico');

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {

    // 🩺 Especialidades: cualquier rol operativo necesita leerlas para los desplegables,
    // pero solo admin puede darlas de alta.
    Route::prefix('especialidades')->group(function () {
        Route::get('/', [SpecialtyController::class, 'index']);
        Route::post('/', [SpecialtyController::class, 'store'])->middleware('role:admin');
    });

    Route::middleware(['role:mesa de entrada|admin'])->prefix('entrada')->group(function () {
        Route::post('turnos', [EntranceController::class, 'store']);
    });

    Route::middleware(['role:preconsulta|admin'])->prefix('preconsulta')->group(function () {
        Route::get('turnos', [PreconsultaController::class, 'index']);
        Route::post('turnos/{appointment}/complete', [PreconsultaController::class, 'complete']);
    });

    Route::middleware(['role:profesional'])->prefix('profesional')->group(function () {
        Route::get('cola', [ProfessionalController::class, 'queue']);
        Route::post('turnos/{appointment}/call', [ProfessionalController::class, 'call']);
        Route::post('turnos/{appointment}/attend', [ProfessionalController::class, 'attend']);
        Route::post('turnos/{appointment}/absent', [ProfessionalController::class, 'absent']);
    });

    // 🗼 Torre de Control: tablero en vivo de las salas, solo para admin.
    Route::middleware(['role:admin'])->prefix('admin')->group(function () {
        Route::get('torre-control', [ControlTowerController::class, 'index']);
        Route::post('salas/{room}/limpieza', [ControlTowerController::class, 'toggleLimpieza']);
    });
});
