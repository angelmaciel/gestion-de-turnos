<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configurarLimitesDePeticiones();
        $this->configurarPoliticaDeContrasenas();

        // Fuera de desarrollo, nunca emitir URLs en http: los tokens viajarían en claro.
        if (! $this->app->environment('local', 'testing')) {
            URL::forceScheme('https');
        }
    }

    /**
     * Sin esto el login acepta intentos ilimitados: una contraseña como
     * 'password123' se descubre por fuerza bruta en segundos.
     */
    private function configurarLimitesDePeticiones(): void
    {
        // Doble llave: por cuenta atacada y por origen. Así un atacante no puede
        // dejar a un usuario legítimo fuera del sistema saturando solo su email.
        RateLimiter::for('login', function (Request $request) {
            $email = (string) $request->input('email');

            return [
                Limit::perMinute(5)->by('login|'.mb_strtolower($email).'|'.$request->ip()),
                Limit::perMinute(20)->by('login-ip|'.$request->ip()),
            ];
        });

        // Techo general de la API, para frenar scraping de datos de pacientes.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)
            ->by($request->user()?->id ?: $request->ip()));

        // La pantalla de sala de espera es pública: se limita aparte y más fuerte,
        // porque expone nombres de pacientes sin autenticación.
        RateLimiter::for('publico', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));
    }

    /**
     * Requisito mínimo para cualquier contraseña de usuario del sistema.
     * Se aplica con Password::defaults() en las validaciones.
     */
    private function configurarPoliticaDeContrasenas(): void
    {
        Password::defaults(function () {
            $regla = Password::min(12)
                ->letters()
                ->mixedCase()
                ->numbers()
                ->symbols();

            // En producción además se rechazan contraseñas filtradas en brechas
            // conocidas (consulta k-anonimizada a haveibeenpwned).
            return $this->app->isProduction() ? $regla->uncompromised() : $regla;
        });
    }
}
