<?php

namespace App\Policies;

use App\Models\Categoria;
use App\Models\User;
use App\Policies\Concerns\ChecaAdminCentralDelTenant;

class CategoriaPolicy
{
    use ChecaAdminCentralDelTenant;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Categoria $categoria): bool
    {
        return $user->tieneAccesoAEmpresa($categoria->empresa);
    }

    public function create(User $user): bool
    {
        return $this->esAdminCentralDelTenant($user);
    }

    public function update(User $user, Categoria $categoria): bool
    {
        return $user->esAdminCentralDe($categoria->empresa);
    }

    public function delete(User $user, Categoria $categoria): bool
    {
        return $user->esAdminCentralDe($categoria->empresa);
    }

    public function deleteAny(User $user): bool
    {
        return $this->esAdminCentralDelTenant($user);
    }
}
