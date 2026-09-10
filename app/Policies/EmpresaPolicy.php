<?php

namespace App\Policies;

use App\Models\Empresa;
use App\Models\User;

class EmpresaPolicy
{
    public function view(User $user, Empresa $empresa): bool
    {
        return $user->tieneAccesoAEmpresa($empresa);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Empresa $empresa): bool
    {
        return $user->esAdminCentralDe($empresa);
    }
}
