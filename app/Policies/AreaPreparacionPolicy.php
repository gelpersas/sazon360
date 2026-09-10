<?php

namespace App\Policies;

use App\Enums\Rol;
use App\Models\AreaPreparacion;
use App\Models\User;
use Filament\Facades\Filament;

class AreaPreparacionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, AreaPreparacion $area): bool
    {
        return $user->rolEnSede($area->sede) !== null;
    }

    public function create(User $user): bool
    {
        $empresa = Filament::getTenant();

        if (! $empresa) {
            return false;
        }

        return $user->esAdminCentralDe($empresa) || $user->sedesAccesibles($empresa)->isNotEmpty();
    }

    public function update(User $user, AreaPreparacion $area): bool
    {
        return $user->rolEnSede($area->sede) !== null;
    }

    public function delete(User $user, AreaPreparacion $area): bool
    {
        return $user->rolEnSede($area->sede) === Rol::AdministracionCentral
            || $user->rolEnSede($area->sede) === Rol::AdministracionSede;
    }

    public function deleteAny(User $user): bool
    {
        $empresa = Filament::getTenant();

        return $empresa && ($user->esAdminCentralDe($empresa) || $user->sedesAccesibles($empresa)->isNotEmpty());
    }
}
