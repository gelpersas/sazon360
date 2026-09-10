<?php

namespace App\Filament\Pages;

use App\Enums\EstadoPedido;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\Sede;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Reporte de ventas consolidado entre sedes de la empresa activa — criterio
 * de terminación de Fase 8 ("un reporte consolidado cruza datos de 2+
 * sedes", ver docs/ROADMAP.md). Solo administración central: cruzar ventas
 * de todas las sedes es información a nivel de empresa, no de una sede
 * puntual (mismo criterio que otros catálogos de solo-central).
 */
class ReporteConsolidado extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static ?string $navigationLabel = 'Reporte consolidado';

    protected string $view = 'filament.pages.reporte-consolidado';

    public string $desde;

    public string $hasta;

    public static function canAccess(): bool
    {
        $empresa = Filament::getTenant();
        $user = Auth::user();

        return $empresa !== null && $user !== null && $user->esAdminCentralDe($empresa);
    }

    public function mount(): void
    {
        $this->desde = now()->startOfMonth()->toDateString();
        $this->hasta = now()->toDateString();
    }

    public function getTitle(): string
    {
        return 'Reporte consolidado';
    }

    public function actualizar(): void
    {
        // Livewire ya sincronizó $desde/$hasta al recibir el submit — este
        // método solo existe para que el botón dispare una nueva petición
        // (y con ella, un recálculo de la propiedad computada `filas`).
    }

    /**
     * @return Collection<int, array{sede: Sede, total_vendido: string, pedidos_cobrados: int, ticket_promedio: string}>
     */
    public function getFilasProperty(): Collection
    {
        $empresa = Filament::getTenant();

        if ($empresa === null) {
            return collect();
        }

        $rango = ["{$this->desde} 00:00:00", "{$this->hasta} 23:59:59"];

        return $empresa->sedes->map(function (Sede $sede) use ($rango) {
            $totalVendido = bcadd(
                (string) Pago::whereHas('pedido', fn ($query) => $query->where('sede_id', $sede->id))
                    ->whereBetween('created_at', $rango)
                    ->sum('monto'),
                '0',
                2,
            );

            $pedidosCobrados = Pedido::where('sede_id', $sede->id)
                ->where('estado', EstadoPedido::Cobrado)
                ->whereBetween('cerrado_at', $rango)
                ->count();

            $ticketPromedio = $pedidosCobrados > 0
                ? bcdiv($totalVendido, (string) $pedidosCobrados, 2)
                : '0.00';

            return [
                'sede' => $sede,
                'total_vendido' => $totalVendido,
                'pedidos_cobrados' => $pedidosCobrados,
                'ticket_promedio' => $ticketPromedio,
            ];
        });
    }

    public function getTotalConsolidadoProperty(): string
    {
        return $this->filas->reduce(
            fn (string $acumulado, array $fila) => bcadd($acumulado, $fila['total_vendido'], 2),
            '0.00',
        );
    }

    public function getPedidosConsolidadosProperty(): int
    {
        return $this->filas->sum('pedidos_cobrados');
    }
}
