<?php

namespace App\Policies;

use App\Models\Pedido;
use App\Models\User;

/**
 * Comanda y Pago no tienen Policy propia: ambas cuelgan de un Pedido (que a
 * su vez cuelga de una Sede), así que se autorizan igual que aquí —
 * `rolEnSede($registro->pedido->sede) !== null` — directo en el controlador,
 * para no multiplicar clases casi idénticas (ver docs/MODULO-ACTUAL.md).
 */
class PedidoPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Pedido $pedido): bool
    {
        return $user->rolEnSede($pedido->sede) !== null;
    }

    public function update(User $user, Pedido $pedido): bool
    {
        return $user->rolEnSede($pedido->sede) !== null;
    }
}
