<?php

namespace App\Http\Controllers\Pos;

use App\Enums\TemaPreferencia;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\LoginRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(LoginRequest $request): array
    {
        $credenciales = $request->validated();

        if (! Auth::guard('web')->attempt($credenciales)) {
            throw ValidationException::withMessages([
                'email' => 'Las credenciales no coinciden con ningún usuario.',
            ]);
        }

        $request->session()->regenerate();

        return $this->respuestaUsuarioActual($request);
    }

    public function logout(Request $request): array
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return ['ok' => true];
    }

    public function me(Request $request): array
    {
        return $this->respuestaUsuarioActual($request);
    }

    /**
     * Guarda la preferencia de tema del POS del lado del servidor (misma
     * columna que usa Filament — ver User::actualizarTema()) para que siga
     * al usuario entre dispositivos, no solo en el localStorage del
     * navegador actual.
     */
    public function actualizarTema(Request $request): array
    {
        $data = $request->validate([
            'tema' => ['required', Rule::enum(TemaPreferencia::class)],
        ]);

        /** @var User $user */
        $user = $request->user();
        $user->actualizarTema(TemaPreferencia::from($data['tema']));

        return $this->respuestaUsuarioActual($request);
    }

    private function respuestaUsuarioActual(Request $request): array
    {
        /** @var User $user */
        $user = $request->user();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'tema' => $user->tema->value,
            // 'roles' (plural): un usuario puede tener más de un rol en la
            // misma sede si el administrador se lo asigna (ver
            // User::rolesEnSede()) — el POS decide qué mostrar según TODOS
            // los roles, no solo "el" primero.
            'sedes' => $user->sedesOperativas()->map(fn ($sede) => [
                'id' => $sede->id,
                'nombre' => $sede->nombre,
                'empresa_id' => $sede->empresa_id,
                'roles' => $user->rolesEnSede($sede)->map(fn ($rol) => $rol->value)->values(),
            ])->values(),
        ];
    }
}
