<?php

namespace App\Policies;

use App\Models\Compra;
use App\Models\User;
use Filament\Facades\Filament;

/**
 * Mismo criterio que CajaPolicy: cualquiera con acceso a alguna sede puede
 * registrar una compra (la recibe físicamente quien esté en la sede, no
 * necesariamente administración central).
 */
class CompraPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Compra $compra): bool
    {
        return $user->rolEnSede($compra->sede) !== null;
    }

    public function create(User $user): bool
    {
        $empresa = Filament::getTenant();

        if (! $empresa) {
            return false;
        }

        return $user->esAdminCentralDe($empresa) || $user->sedesAccesibles($empresa)->isNotEmpty();
    }
}
