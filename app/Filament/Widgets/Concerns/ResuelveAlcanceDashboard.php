<?php

namespace App\Filament\Widgets\Concerns;

use App\Models\Sede;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Alcance común a los widgets del dashboard de administración (ver
 * docs/DECISIONES.md DEC-065) — requiere que la clase use
 * `Filament\Widgets\Concerns\InteractsWithPageFilters` (expone
 * `$this->pageFilters`, sincronizado con App\Filament\Pages\Dashboard).
 */
trait ResuelveAlcanceDashboard
{
    /**
     * Sedes accesibles por el usuario dentro del tenant activo — todas
     * (vista consolidada) o solo la elegida en el filtro "Sede" (drill-down).
     *
     * @return Collection<int, Sede>
     */
    protected function sedesEnAlcance(): Collection
    {
        $empresa = Filament::getTenant();

        if (! $empresa) {
            return collect();
        }

        $accesibles = Auth::user()->sedesAccesibles($empresa);

        $sedeId = $this->pageFilters['sede_id'] ?? null;

        return $sedeId ? $accesibles->where('id', $sedeId)->values() : $accesibles;
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: string}
     */
    protected function rangoSeleccionado(): array
    {
        return match ($this->pageFilters['rango'] ?? 'hoy') {
            'semana' => [now()->startOfWeek(), now()->endOfDay(), 'esta semana'],
            'mes' => [now()->startOfMonth(), now()->endOfDay(), 'este mes'],
            default => [now()->startOfDay(), now()->endOfDay(), 'hoy'],
        };
    }
}
