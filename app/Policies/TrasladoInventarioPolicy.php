<?php

namespace App\Policies;

use App\Models\TrasladoInventario;
use App\Models\User;
use Filament\Facades\Filament;

/**
 * Un traslado toca dos sedes — a diferencia de Caja/Compra (una sola sede),
 * aquí se exige acceso a AMBAS (origen y destino) para poder registrarlo:
 * evita que alguien con acceso solo a una de las dos mueva inventario hacia
 * o desde una sede que no administra. Administración central siempre puede
 * (tiene acceso a todas las sedes de su empresa).
 */
class TrasladoInventarioPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, TrasladoInventario $traslado): bool
    {
        return $user->rolEnSede($traslado->sedeOrigen) !== null || $user->rolEnSede($traslado->sedeDestino) !== null;
    }

    public function create(User $user): bool
    {
        $empresa = Filament::getTenant();

        if (! $empresa) {
            return false;
        }

        return $user->esAdminCentralDe($empresa) || $user->sedesAccesibles($empresa)->count() >= 2;
    }
}
