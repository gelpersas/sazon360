<?php

namespace App\Http\Controllers;

use App\Enums\TemaPreferencia;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Guarda la preferencia de tema (claro/oscuro/auto) del usuario autenticado
 * en Filament — mismo campo que el POS táctil actualiza vía
 * `PATCH /api/pos/me/tema` (ambas superficies comparten `users.tema`, ver
 * User::actualizarTema()). Se llama desde un pequeño script inline
 * enganchado al evento `theme-changed` que ya dispara el selector de tema
 * nativo de Filament (ver `resources/views/filament/tema-sync.blade.php`).
 */
class TemaController extends Controller
{
    public function actualizar(Request $request): array
    {
        $data = $request->validate([
            'tema' => ['required', Rule::enum(TemaPreferencia::class)],
        ]);

        /** @var User $user */
        $user = $request->user();
        $user->actualizarTema(TemaPreferencia::from($data['tema']));

        return ['ok' => true];
    }
}
