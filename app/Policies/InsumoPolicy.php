<?php

namespace App\Policies;

use App\Models\Insumo;
use App\Models\User;
use App\Policies\Concerns\ChecaAdminCentralDelTenant;

class InsumoPolicy
{
    use ChecaAdminCentralDelTenant;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Insumo $insumo): bool
    {
        return $user->tieneAccesoAEmpresa($insumo->empresa);
    }

    public function create(User $user): bool
    {
        return $this->esAdminCentralDelTenant($user);
    }

    public function update(User $user, Insumo $insumo): bool
    {
        return $user->esAdminCentralDe($insumo->empresa);
    }

    public function delete(User $user, Insumo $insumo): bool
    {
        return $user->esAdminCentralDe($insumo->empresa);
    }

    public function deleteAny(User $user): bool
    {
        return $this->esAdminCentralDelTenant($user);
    }
}
