<?php

namespace App\Policies;

use App\Enums\Rol;
use App\Models\Sede;
use App\Models\User;
use App\Policies\Concerns\ChecaAdminCentralDelTenant;

class SedePolicy
{
    use ChecaAdminCentralDelTenant;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Sede $sede): bool
    {
        return $user->rolEnSede($sede) !== null;
    }

    public function create(User $user): bool
    {
        return $this->esAdminCentralDelTenant($user);
    }

    public function update(User $user, Sede $sede): bool
    {
        return in_array($user->rolEnSede($sede), [Rol::AdministracionCentral, Rol::AdministracionSede], true);
    }

    public function delete(User $user, Sede $sede): bool
    {
        return $user->rolEnSede($sede) === Rol::AdministracionCentral;
    }

    public function deleteAny(User $user): bool
    {
        return $this->esAdminCentralDelTenant($user);
    }
}
