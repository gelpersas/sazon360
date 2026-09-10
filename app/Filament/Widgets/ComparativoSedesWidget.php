<?php

namespace App\Filament\Widgets;

use App\Enums\EstadoPedido;
use App\Filament\Widgets\Concerns\ResuelveAlcanceDashboard;
use App\Models\ItemPedido;
use App\Models\Pago;
use App\Models\Sede;
use App\Models\User;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Comparativo por sede (ver docs/DECISIONES.md DEC-065) — con una sola sede
 * en alcance (drill-down, o administración de sede con una sola sede
 * accesible) muestra una única fila: la misma tabla sirve para el nivel
 * consolidado y el drill-down sin necesitar dos widgets separados.
 *
 * Criterios confirmados con el usuario: "producto top" = mayor CANTIDAD
 * vendida (no monto); "empleado destacado" = mayor MONTO cobrado
 * (`Pago.usuario_id`, mismo campo que ya usa ReporteConsolidado).
 */
class ComparativoSedesWidget extends Widget
{
    use InteractsWithPageFilters;
    use ResuelveAlcanceDashboard;

    protected string $view = 'filament.widgets.comparativo-sedes-widget';

    protected int|string|array $columnSpan = 'full';

    /**
     * @return Collection<int, array{sede: Sede, monto_vendido: string, producto_top: ?string, empleado_destacado: ?string}>
     */
    public function getFilasProperty(): Collection
    {
        [$desde, $hasta] = $this->rangoSeleccionado();

        return $this->sedesEnAlcance()->map(function (Sede $sede) use ($desde, $hasta) {
            $montoVendido = bcadd(
                (string) Pago::whereHas('pedido', fn ($query) => $query->where('sede_id', $sede->id))
                    ->whereBetween('created_at', [$desde, $hasta])
                    ->sum('monto'),
                '0',
                2,
            );

            return [
                'sede' => $sede,
                'monto_vendido' => $montoVendido,
                'producto_top' => $this->productoTop($sede, $desde, $hasta),
                'empleado_destacado' => $this->empleadoDestacado($sede, $desde, $hasta),
            ];
        });
    }

    private function productoTop(Sede $sede, $desde, $hasta): ?string
    {
        $fila = ItemPedido::query()
            ->whereHas('pedido', fn ($query) => $query->where('sede_id', $sede->id)
                ->where('estado', EstadoPedido::Cobrado)
                ->whereBetween('cerrado_at', [$desde, $hasta]))
            ->select('nombre_producto', DB::raw('SUM(cantidad) as total_cantidad'))
            ->groupBy('nombre_producto')
            ->orderByDesc('total_cantidad')
            ->first();

        return $fila?->nombre_producto;
    }

    private function empleadoDestacado(Sede $sede, $desde, $hasta): ?string
    {
        $fila = Pago::query()
            ->whereHas('pedido', fn ($query) => $query->where('sede_id', $sede->id))
            ->whereBetween('created_at', [$desde, $hasta])
            ->select('usuario_id', DB::raw('SUM(monto) as total_monto'))
            ->groupBy('usuario_id')
            ->orderByDesc('total_monto')
            ->first();

        return $fila ? User::find($fila->usuario_id)?->name : null;
    }
}
