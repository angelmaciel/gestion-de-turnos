<?php

use App\Models\AuditLog;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['mesa de entrada', 'preconsulta', 'profesional', 'admin'] as $role) {
        Role::findOrCreate($role, 'web');
        Role::findOrCreate($role, 'sanctum');
    }
});

/**
 * Sanctum decide si una petición es "stateful" mirando el Origin/Referer
 * contra SANCTUM_STATEFUL_DOMAINS. Sin esa cabecera, el test se comporta como
 * un cliente de API (token) y nunca abre sesión.
 */
function desdeElSpa(): array
{
    return ['Origin' => config('app.frontend_url', 'http://localhost:5173')];
}

it('autentica por sesión y devuelve el usuario sin token', function () {
    $user = usuarioCon('admin');

    $respuesta = $this->withHeaders(desdeElSpa())->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password123',
    ])->assertOk();

    // Con sesión activa no se emite token: nada que el frontend pueda guardar.
    $respuesta->assertJsonPath('user.email', $user->email)
        ->assertJsonMissingPath('token');

    expect(auth()->guard('web')->check())->toBeTrue();
});

it('expone el usuario autenticado en /auth/me', function () {
    $user = usuarioCon('preconsulta');

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonPath('user.email', $user->email)
        ->assertJsonPath('user.roles.0', 'preconsulta');
});

it('devuelve 401 en /auth/me sin sesión', function () {
    $this->getJson('/api/auth/me')->assertUnauthorized();
});

it('responde 401 y no un 500 aunque no se pida JSON', function () {
    // Sin Accept: application/json, Laravel intenta redirigir a la ruta 'login'.
    // Si esa ruta no existe, devuelve un 500 con traza en vez de un 401.
    $this->get('/api/auth/me')->assertStatus(401);
});

it('no expone endpoints que vuelquen datos de pacientes sin autenticación', function () {
    // Existieron rutas /test/* que devolvían la base entera con las cédulas y
    // los datos clínicos ya descifrados. No deben volver.
    foreach (['/test/patients', '/test/appointments', '/test/professionals'] as $ruta) {
        $this->get($ruta)->assertNotFound();
    }
});

it('no revela si el email existe al fallar el login', function () {
    $user = usuarioCon('admin');

    $conEmailReal = $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'incorrecta',
    ])->assertStatus(401);

    $conEmailInexistente = $this->postJson('/api/auth/login', [
        'email' => 'noexiste@ejemplo.com',
        'password' => 'incorrecta',
    ])->assertStatus(401);

    // Mismo mensaje: distinguirlos permitiría enumerar cuentas.
    expect($conEmailReal->json('message'))->toBe($conEmailInexistente->json('message'));
});

it('cierra la sesión y deja de autenticar', function () {
    $user = usuarioCon('admin');

    $this->withHeaders(desdeElSpa())->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password123',
    ])->assertOk();

    $this->withHeaders(desdeElSpa())->postJson('/api/auth/logout')->assertOk();

    expect(auth()->guard('web')->check())->toBeFalse();
});

it('registra en auditoría los intentos de login', function () {
    $user = usuarioCon('admin');

    $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'mal'])->assertStatus(401);
    $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password123'])->assertOk();

    $acciones = AuditLog::orderBy('id')->pluck('accion')->all();

    expect($acciones)->toContain('login_fallido')
        ->toContain('login_exitoso');
});

it('bloquea la fuerza bruta tras varios intentos fallidos', function () {
    $user = usuarioCon('admin');

    // El limitador es 5 por minuto por email+IP (ver AppServiceProvider).
    foreach (range(1, 5) as $i) {
        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'incorrecta',
        ])->assertStatus(401);
    }

    $this->withHeaders(desdeElSpa())->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'incorrecta',
    ])->assertStatus(429);
})->skip(fn () => config('cache.default') === 'array', 'Requiere un limitador persistente.');
