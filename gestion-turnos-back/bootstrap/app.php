<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Sesión por cookie HttpOnly para el SPA. Este middleware arranca la
        // sesión y valida el token CSRF SOLO cuando la petición viene de un
        // dominio declarado en SANCTUM_STATEFUL_DOMAINS. Las peticiones con
        // Bearer token (por ejemplo desde otro backend) lo atraviesan sin
        // sesión, así ambos mecanismos conviven.
        //
        // Ojo: acá NO se excluye 'api/*' de la validación CSRF. Con
        // autenticación por cookie, hacerlo dejaría la API expuesta a CSRF:
        // el navegador adjunta la cookie sola en peticiones de otros sitios.
        $middleware->api(prepend: [
            EnsureFrontendRequestsAreStateful::class,
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Sin esto, una petición no autenticada a la API que no declare
        // Accept: application/json intenta redirigir a la ruta 'login' (que no
        // existe en una API) y termina en un 500 con traza en vez de un 401.
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'No autenticado.'], 401);
            }

            return null;
        });
    })->create();
