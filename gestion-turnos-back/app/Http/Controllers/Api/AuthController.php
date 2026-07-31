<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    /**
     * Inicia sesión.
     *
     * Si la petición viene del SPA (dominio declarado en
     * SANCTUM_STATEFUL_DOMAINS), autentica por sesión: la cookie resultante es
     * HttpOnly, así que ningún script puede leerla y un XSS no puede robar la
     * sesión. Es el mecanismo que usa el frontend.
     *
     * Para clientes que no son navegadores se sigue emitiendo un token Bearer.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credenciales = $request->validated();

        $user = User::where('email', $credenciales['email'])->first();

        // Mensaje idéntico exista o no el usuario: distinguirlos permitiría
        // enumerar qué cuentas existen en el sistema.
        if (! $user || ! Hash::check($credenciales['password'], $user->password)) {
            AuditLog::registrar('login_fallido', null, ['email' => $credenciales['email']]);

            return response()->json(['message' => 'Credenciales inválidas.'], 401);
        }

        $respuesta = [
            'message' => 'Autenticación exitosa',
            'user' => $this->datosDeUsuario($user),
        ];

        if ($request->hasSession()) {
            Auth::guard('web')->login($user, remember: false);
            // Renovar el id de sesión corta cualquier intento de session fixation.
            $request->session()->regenerate();
        } else {
            $respuesta['token'] = $user->createToken('api-token')->plainTextToken;
            $respuesta['token_type'] = 'Bearer';
        }

        AuditLog::registrar('login_exitoso');

        return response()->json($respuesta);
    }

    /**
     * Usuario autenticado.
     *
     * El frontend lo consulta en cada carga en vez de guardar los datos del
     * usuario en localStorage, donde cualquier script podría leerlos y donde
     * quedarían obsoletos si cambian los roles.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        return response()->json(['user' => $this->datosDeUsuario($user)]);
    }

    public function logout(Request $request): JsonResponse
    {
        AuditLog::registrar('logout');

        // Si la autenticación fue por token, se revoca el usado. Con sesión,
        // currentAccessToken() devuelve un TransientToken que no se persiste
        // y por lo tanto no se puede borrar.
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        if ($request->hasSession()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['message' => 'Sesión cerrada']);
    }

    /** @return array<string, mixed> */
    private function datosDeUsuario(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->getRoleNames(),
            'especialidad' => $user->professional?->specialty?->name,
            'sala' => $user->professional?->room?->name,
        ];
    }
}
