<?php

namespace App\Policies;

use App\Enums\Rol;
use App\Models\Mesa;
use App\Models\User;
use Filament\Facades\Filament;

class MesaPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Mesa $mesa): bool
    {
        return $user->rolEnSede($mesa->sede) !== null;
    }

    public function create(User $user): bool
    {
        $empresa = Filament::getTenant();

        if (! $empresa) {
            return false;
        }

        return $user->esAdminCentralDe($empresa) || $user->sedesAccesibles($empresa)->isNotEmpty();
    }

    public function update(User $user, Mesa $mesa): bool
    {
        return $user->rolEnSede($mesa->sede) !== null;
    }

    public function delete(User $user, Mesa $mesa): bool
    {
        return $user->rolEnSede($mesa->sede) === Rol::AdministracionCentral
            || $user->rolEnSede($mesa->sede) === Rol::AdministracionSede;
    }

    public function deleteAny(User $user): bool
    {
        $empresa = Filament::getTenant();

        return $empresa && ($user->esAdminCentralDe($empresa) || $user->sedesAccesibles($empresa)->isNotEmpty());
    }
}
