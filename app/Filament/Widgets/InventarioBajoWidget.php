<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ResuelveAlcanceDashboard;
use App\Models\Inventario;
use App\Models\Sede;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/**
 * Alertas de stock bajo del alcance vigente (ver docs/DECISIONES.md
 * DEC-065) — reutiliza Inventario::bajoMinimo() (Fase 5) sin lógica nueva.
 * Consolidado: cada alerta indica a qué sede pertenece.
 */
class InventarioBajoWidget extends Widget
{
    use InteractsWithPageFilters;
    use ResuelveAlcanceDashboard;

    protected string $view = 'filament.widgets.inventario-bajo-widget';

    protected int|string|array $columnSpan = 'full';

    /**
     * @return Collection<int, array{sede: string, insumo: string, cantidad_actual: string, stock_minimo: string}>
     */
    public function getAlertasProperty(): Collection
    {
        return $this->sedesEnAlcance()->flatMap(function (Sede $sede) {
            return Inventario::where('sede_id', $sede->id)
                ->with('insumo')
                ->get()
                ->filter(fn (Inventario $inventario) => $inventario->bajoMinimo())
                ->map(fn (Inventario $inventario) => [
                    'sede' => $sede->nombre,
                    'insumo' => $inventario->insumo->nombre,
                    'cantidad_actual' => (string) $inventario->cantidad_actual,
                    'stock_minimo' => (string) $inventario->insumo->stock_minimo,
                ]);
        })->values();
    }
}
