<?php

namespace App\Filament\Widgets;

use App\Enums\EstadoPedido;
use App\Filament\Widgets\Concerns\ResuelveAlcanceDashboard;
use App\Models\Comanda;
use App\Models\Mesa;
use App\Models\Pago;
use App\Models\Pedido;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Resumen del alcance vigente (una sede en drill-down, o todas las
 * accesibles en consolidado — ver App\Filament\Pages\Dashboard y
 * docs/DECISIONES.md DEC-065). "Ticket promedio" reutiliza el mismo
 * criterio que App\Filament\Pages\ReporteConsolidado (Fase 8).
 */
class VentasResumenWidget extends StatsOverviewWidget
{
    use InteractsWithPageFilters;
    use ResuelveAlcanceDashboard;

    protected function getStats(): array
    {
        $sedes = $this->sedesEnAlcance();
        $sedeIds = $sedes->pluck('id');
        [$desde, $hasta, $etiquetaRango] = $this->rangoSeleccionado();

        $ventasTotal = bcadd(
            (string) Pago::whereHas('pedido', fn ($query) => $query->whereIn('sede_id', $sedeIds))
                ->whereBetween('created_at', [$desde, $hasta])
                ->sum('monto'),
            '0',
            2,
        );

        $pedidosCobrados = Pedido::whereIn('sede_id', $sedeIds)
            ->where('estado', EstadoPedido::Cobrado)
            ->whereBetween('cerrado_at', [$desde, $hasta])
            ->count();

        $ticketPromedio = $pedidosCobrados > 0 ? bcdiv($ventasTotal, (string) $pedidosCobrados, 2) : '0.00';

        $pedidosActivos = Comanda::whereHas('pedido', fn ($query) => $query->whereIn('sede_id', $sedeIds))
            ->whereIn('estado', ['pendiente', 'en_preparacion', 'lista'])
            ->count();

        $descripcionAlcance = $sedes->count() > 1 ? 'Todas las sedes' : ($sedes->first()->nombre ?? 'Sin sede');

        return [
            Stat::make("Ventas ({$etiquetaRango})", "\${$ventasTotal}")->description($descripcionAlcance),
            Stat::make('Ticket promedio', "\${$ticketPromedio}"),
            Stat::make('Pedidos activos', $pedidosActivos)->description('En cocina, sin entregar'),
            Stat::make('Mesas ocupadas', Mesa::ocupadasEnSedes($sedeIds)),
        ];
    }
}
