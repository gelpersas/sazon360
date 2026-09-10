<?php

namespace App\Policies;

use App\Models\Caja;
use App\Models\User;
use Filament\Facades\Filament;

class CajaPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Caja $caja): bool
    {
        return $user->rolEnSede($caja->sede) !== null;
    }

    public function create(User $user): bool
    {
        $empresa = Filament::getTenant();

        if (! $empresa) {
            return false;
        }

        return $user->esAdminCentralDe($empresa) || $user->sedesAccesibles($empresa)->isNotEmpty();
    }

    /**
     * Cerrar una caja usa esta misma autorización que verla — cualquiera con
     * acceso a la sede puede cerrarla, no solo quien la abrió (ver
     * docs/REGLAS-NEGOCIO.md, "Caja"). Reapertura no está implementada
     * todavía (fuera de alcance de Fase 3).
     */
    public function cerrar(User $user, Caja $caja): bool
    {
        return $user->rolEnSede($caja->sede) !== null;
    }
}
