<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Comanda;
use App\Models\Inventario;
use App\Models\Mesa;
use App\Models\Pago;
use App\Models\Sede;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Página de inicio del POS táctil (ver docs/DECISIONES.md DEC-065) —
     * mismos datos para cualquier rol con acceso a la sede, el frontend
     * decide qué destacar según el rol. Accesible a cualquiera con AL MENOS
     * un `Acceso` en esta sede (no solo Mostrador/Caja/Cocina), a propósito
     * más permisivo que el resto del POS: son solo métricas de referencia,
     * ningún dato sensible de un pedido puntual.
     */
    public function index(Request $request, Sede $sede): array
    {
        abort_unless($request->user()->rolesEnSede($sede)->isNotEmpty(), 403);

        $rango = $request->query('rango', 'hoy');
        abort_unless(in_array($rango, ['hoy', 'semana', 'mes'], true), 422);

        [$desde, $hasta] = $this->limitesRango($rango);

        $ventasTotal = bcadd(
            (string) Pago::whereHas('pedido', fn ($query) => $query->where('sede_id', $sede->id))
                ->whereBetween('created_at', [$desde, $hasta])
                ->sum('monto'),
            '0',
            2,
        );

        $pedidosActivos = Comanda::whereHas('pedido', fn ($query) => $query->where('sede_id', $sede->id))
            ->whereIn('estado', ['pendiente', 'en_preparacion', 'lista'])
            ->count();

        return [
            'data' => [
                'rango' => $rango,
                'ventas_total' => $ventasTotal,
                'pedidos_activos' => $pedidosActivos,
                'mesas_ocupadas' => Mesa::ocupadasEnSedes(collect([$sede->id])),
                'inventario_bajo' => $this->inventarioBajo($sede),
            ],
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function limitesRango(string $rango): array
    {
        return match ($rango) {
            'hoy' => [now()->startOfDay(), now()->endOfDay()],
            'semana' => [now()->startOfWeek(), now()->endOfDay()],
            'mes' => [now()->startOfMonth(), now()->endOfDay()],
        };
    }

    /**
     * @return array<int, array{insumo: string, cantidad_actual: string, stock_minimo: string}>
     */
    private function inventarioBajo(Sede $sede): array
    {
        return Inventario::where('sede_id', $sede->id)
            ->with('insumo')
            ->get()
            ->filter(fn (Inventario $inventario) => $inventario->bajoMinimo())
            ->map(fn (Inventario $inventario) => [
                'insumo' => $inventario->insumo->nombre,
                'cantidad_actual' => (string) $inventario->cantidad_actual,
                'stock_minimo' => (string) $inventario->insumo->stock_minimo,
            ])
            ->values()
            ->all();
    }
}
