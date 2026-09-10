<?php

namespace App\Policies\Concerns;

use App\Models\User;
use Filament\Facades\Filament;

/**
 * Para policies de recursos que solo administración central puede crear o
 * eliminar en bloque (sin un registro concreto todavía) — usa el tenant
 * actual de Filament, no una empresa recibida por parámetro.
 */
trait ChecaAdminCentralDelTenant
{
    protected function esAdminCentralDelTenant(User $user): bool
    {
        $empresa = Filament::getTenant();

        return $empresa && $user->esAdminCentralDe($empresa);
    }
}
