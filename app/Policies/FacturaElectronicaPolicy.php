<?php

namespace App\Policies;

use App\Enums\Rol;
use App\Models\FacturaElectronica;
use App\Models\User;

class FacturaElectronicaPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, FacturaElectronica $factura): bool
    {
        return $user->rolEnSede($factura->pedido->sede) !== null;
    }

    /**
     * Usada para autorizar la acción "Reintentar" — solo administración
     * central o administración de la sede del pedido, no cualquier rol con
     * acceso (a diferencia de InventarioPolicy::create()): reintentar una
     * emisión fiscal es una acción más sensible que registrar un movimiento
     * de stock.
     */
    public function update(User $user, FacturaElectronica $factura): bool
    {
        $rol = $user->rolEnSede($factura->pedido->sede);

        return in_array($rol, [Rol::AdministracionCentral, Rol::AdministracionSede], true);
    }
}
