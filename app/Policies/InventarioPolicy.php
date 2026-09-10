<?php

namespace App\Policies;

use App\Models\Inventario;
use App\Models\User;
use Filament\Facades\Filament;

class InventarioPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Inventario $inventario): bool
    {
        return $user->rolEnSede($inventario->sede) !== null;
    }

    /**
     * Usada para autorizar "Registrar movimiento" (misma sede que
     * Mesa/Caja — cualquiera con acceso a la sede, no solo administración).
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
