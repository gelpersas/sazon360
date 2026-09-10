<?php

namespace App\Policies;

use App\Models\Proveedor;
use App\Models\User;
use App\Policies\Concerns\ChecaAdminCentralDelTenant;

/**
 * Mismo criterio que InsumoPolicy: proveedor es catálogo por empresa, solo
 * administración central lo gestiona.
 */
class ProveedorPolicy
{
    use ChecaAdminCentralDelTenant;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Proveedor $proveedor): bool
    {
        return $user->tieneAccesoAEmpresa($proveedor->empresa);
    }

    public function create(User $user): bool
    {
        return $this->esAdminCentralDelTenant($user);
    }

    public function update(User $user, Proveedor $proveedor): bool
    {
        return $user->esAdminCentralDe($proveedor->empresa);
    }

    public function delete(User $user, Proveedor $proveedor): bool
    {
        return $user->esAdminCentralDe($proveedor->empresa);
    }

    public function deleteAny(User $user): bool
    {
        return $this->esAdminCentralDelTenant($user);
    }
}
