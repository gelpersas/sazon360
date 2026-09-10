<?php

namespace App\Policies;

use App\Enums\Rol;
use App\Models\Acceso;
use App\Models\User;
use Filament\Facades\Filament;

class AccesoPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Acceso $acceso): bool
    {
        return $this->puedeGestionar($user, $acceso);
    }

    public function create(User $user): bool
    {
        $empresa = Filament::getTenant();

        if (! $empresa) {
            return false;
        }

        return $user->esAdminCentralDe($empresa) || $user->sedesAccesibles($empresa)->isNotEmpty();
    }

    public function update(User $user, Acceso $acceso): bool
    {
        return $this->puedeGestionar($user, $acceso);
    }

    public function delete(User $user, Acceso $acceso): bool
    {
        return $this->puedeGestionar($user, $acceso);
    }

    public function deleteAny(User $user): bool
    {
        $empresa = Filament::getTenant();

        return $empresa !== null && ($user->esAdminCentralDe($empresa) || $user->sedesAccesibles($empresa)->isNotEmpty());
    }

    /**
     * Administración central gestiona cualquier acceso de su empresa.
     * Administración de sede solo gestiona accesos de sus propias sedes, y
     * nunca uno de administración central — evita que un admin de sede vea,
     * edite o borre el acceso de quien administra toda la empresa.
     */
    private function puedeGestionar(User $user, Acceso $acceso): bool
    {
        if ($user->esAdminCentralDe($acceso->empresa)) {
            return true;
        }

        if ($acceso->rol === Rol::AdministracionCentral) {
            return false;
        }

        return $acceso->sede_id !== null
            && $user->sedesAccesibles($acceso->empresa)->contains('id', $acceso->sede_id);
    }
}
