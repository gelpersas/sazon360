<?php

namespace App\Policies;

use App\Models\Cliente;
use App\Models\User;
use App\Policies\Concerns\ChecaAdminCentralDelTenant;

/**
 * Mismo criterio que ProveedorPolicy: catálogo por empresa, solo
 * administración central lo gestiona desde el panel. La creación/búsqueda
 * desde el POS táctil (al cobrar) usa una autorización distinta —
 * `Rol::accedeACaja()`, ver Pos\ClienteController — porque ahí la crea
 * quien está cobrando, no necesariamente un administrador.
 */
class ClientePolicy
{
    use ChecaAdminCentralDelTenant;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Cliente $cliente): bool
    {
        return $user->tieneAccesoAEmpresa($cliente->empresa);
    }

    public function create(User $user): bool
    {
        return $this->esAdminCentralDelTenant($user);
    }

    public function update(User $user, Cliente $cliente): bool
    {
        return $user->esAdminCentralDe($cliente->empresa);
    }

    public function delete(User $user, Cliente $cliente): bool
    {
        return $user->esAdminCentralDe($cliente->empresa);
    }

    public function deleteAny(User $user): bool
    {
        return $this->esAdminCentralDelTenant($user);
    }
}
