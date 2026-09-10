<?php

use App\Models\Sede;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/**
 * Autorización de canales privados de WebSockets (Reverb) — ver
 * docs/DECISIONES.md (Fase 8). El usuario autenticado (guard `web`, mismo
 * login del POS — ver AuthController) debe tener algún rol en la sede del
 * canal, igual que las rutas de la API del POS.
 */
Broadcast::channel('sede.{sedeId}.comandas', function (User $user, int $sedeId) {
    $sede = Sede::find($sedeId);

    return $sede !== null && $user->rolEnSede($sede) !== null;
});
