<?php

namespace App\Policies;

use App\Models\MovimientoCaja;
use App\Models\User;
use Filament\Facades\Filament;

class MovimientoCajaPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, MovimientoCaja $movimiento): bool
    {
        return $user->rolEnSede($movimiento->caja->sede) !== null;
    }

    /**
     * Filament no pasa la Caja "dueña" a este chequeo genérico (solo decide
     * si mostrar el botón "Registrar movimiento"), así que aquí solo se
     * valida acceso a alguna sede del tenant — igual que MesaPolicy::create().
     * La caja concreta (¿es la mía?, ¿sigue abierta?) se valida en
     * MovimientoCajaRelationManager (visibilidad del botón) y, como defensa
     * adicional, en MovimientoCaja::booted() al guardar.
     */
    public function create(User $user): bool
    {
        $empresa = Filament::getTenant();

        if (! $empresa) {
            return false;
        }

        return $user->esAdminCentralDe($empresa) || $user->sedesAccesibles($empresa)->isNotEmpty();
    }
}
