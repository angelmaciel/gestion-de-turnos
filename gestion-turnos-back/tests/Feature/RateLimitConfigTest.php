<?php

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/** @return list<int> */
function topesDe(string $limitador): array
{
    $limites = RateLimiter::limiter($limitador)(Request::create('/api/auth/login', 'POST', ['email' => 'a@example.com']));

    return array_map(fn (Limit $l) => $l->maxAttempts, is_array($limites) ? $limites : [$limites]);
}

it('mantiene los límites de producción si no se configuran', function () {
    expect(topesDe('login'))->toBe([5, 20])
        ->and(topesDe('api'))->toBe([120])
        ->and(topesDe('publico'))->toBe([30]);
});

it('toma los límites de config/limites.php', function () {
    config([
        'limites.login_por_correo' => 50,
        'limites.login_por_ip' => 500,
        'limites.api' => 10000,
        'limites.publico' => 3000,
    ]);

    expect(topesDe('login'))->toBe([50, 500])
        ->and(topesDe('api'))->toBe([10000])
        ->and(topesDe('publico'))->toBe([3000]);
});
