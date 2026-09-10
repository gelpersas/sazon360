<?php

namespace App\Models;

use App\Enums\EstadoPedido;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Reparte ítems de UN pedido entre varias sub-cuentas ("por productos"/"por
 * personas" — mismo mecanismo, distinto nombre) — no crea una unidad de
 * cobro ni de factura nueva: el pago sigue siendo un `Pago` normal contra el
 * `Pedido` de siempre (solo etiquetado con `sub_cuenta_id`), así que no
 * cambia cuándo el pedido queda `cobrado` ni toca `FacturaElectronica`. Ver
 * docs/DECISIONES.md (Fase 10).
 */
#[Fillable(['empresa_id', 'pedido_id', 'nombre', 'creado_por_id'])]
class SubCuenta extends Model
{
    use HasFactory;

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SubCuentaItem::class);
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class);
    }

    /**
     * @param  array<int, array{item_pedido_id: int, porcentaje: string|float|int}>  $asignaciones
     */
    public static function crear(Pedido $pedido, string $nombre, array $asignaciones, User $usuario): self
    {
        if ($asignaciones === []) {
            throw new InvalidArgumentException('Una sub-cuenta debe tener al menos un ítem asignado.');
        }

        return DB::transaction(function () use ($pedido, $nombre, $asignaciones, $usuario) {
            // Bloquea el pedido completo: evita que dos sub-cuentas creadas
            // a la vez se pasen del 100% de un mismo ítem por una condición
            // de carrera (mismo patrón que Pedido::enviarComanda()).
            $pedidoBloqueado = Pedido::query()->lockForUpdate()->findOrFail($pedido->id);

            if ($pedidoBloqueado->estado !== EstadoPedido::Abierto) {
                throw new InvalidArgumentException('Solo se pueden crear sub-cuentas en un pedido abierto.');
            }

            $subCuenta = static::create([
                'empresa_id' => $pedidoBloqueado->empresa_id,
                'pedido_id' => $pedidoBloqueado->id,
                'nombre' => $nombre,
                'creado_por_id' => $usuario->id,
            ]);

            foreach ($asignaciones as $asignacion) {
                $item = ItemPedido::where('pedido_id', $pedidoBloqueado->id)->findOrFail($asignacion['item_pedido_id']);

                $porcentaje = (string) $asignacion['porcentaje'];

                if (bccomp($porcentaje, '0', 2) <= 0 || bccomp($porcentaje, '100', 2) > 0) {
                    throw new InvalidArgumentException("El porcentaje de \"{$item->nombre_producto}\" debe estar entre 0 y 100.");
                }

                $yaAsignado = SubCuentaItem::whereHas('subCuenta', fn ($query) => $query->where('pedido_id', $pedidoBloqueado->id))
                    ->where('item_pedido_id', $item->id)
                    ->sum('porcentaje');

                if (bccomp(bcadd((string) $yaAsignado, $porcentaje, 2), '100', 2) > 0) {
                    throw new InvalidArgumentException("\"{$item->nombre_producto}\" ya tiene asignado más del 100% entre sus sub-cuentas.");
                }

                SubCuentaItem::create([
                    'sub_cuenta_id' => $subCuenta->id,
                    'item_pedido_id' => $item->id,
                    'porcentaje' => $porcentaje,
                ]);
            }

            return $subCuenta;
        });
    }

    /**
     * Solo se puede deshacer si todavía no tiene pagos — una vez cobrada
     * (aunque sea parcialmente) queda fija, mismo criterio que
     * `Compra`/`MovimientoCaja`.
     */
    public function eliminar(): void
    {
        if (bccomp($this->totalPagado(), '0', 2) > 0) {
            throw new RuntimeException('No se puede eliminar una sub-cuenta que ya tiene pagos registrados.');
        }

        $this->delete();
    }

    public function total(): string
    {
        return $this->items()->get()->reduce(
            fn (string $acumulado, SubCuentaItem $item) => bcadd($acumulado, $item->monto(), 2),
            '0.00',
        );
    }

    public function totalPagado(): string
    {
        return bcadd((string) $this->pagos()->sum('monto'), '0', 2);
    }

    public function saldoPendiente(): string
    {
        return bcsub($this->total(), $this->totalPagado(), 2);
    }
}
