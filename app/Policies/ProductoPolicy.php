<?php

namespace App\Policies;

use App\Models\Producto;
use App\Models\User;
use App\Policies\Concerns\ChecaAdminCentralDelTenant;

class ProductoPolicy
{
    use ChecaAdminCentralDelTenant;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Producto $producto): bool
    {
        return $user->tieneAccesoAEmpresa($producto->empresa);
    }

    public function create(User $user): bool
    {
        return $this->esAdminCentralDelTenant($user);
    }

    public function update(User $user, Producto $producto): bool
    {
        return $user->esAdminCentralDe($producto->empresa);
    }

    public function delete(User $user, Producto $producto): bool
    {
        return $user->esAdminCentralDe($producto->empresa);
    }

    public function deleteAny(User $user): bool
    {
        return $this->esAdminCentralDelTenant($user);
    }
}
