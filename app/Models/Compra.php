<?php

namespace App\Models;

use App\Enums\TipoMovimientoInventario;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

#[Fillable(['empresa_id', 'sede_id', 'proveedor_id', 'usuario_id', 'numero_factura_proveedor', 'notas'])]
class Compra extends Model
{
    use HasFactory;

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CompraItem::class);
    }

    /**
     * Registra una compra completa (encabezado + ítems) y aumenta el
     * inventario de la sede receptora en la misma transacción — a
     * diferencia de Pedido (que se arma incrementalmente en el POS), una
     * compra se captura de una sola vez desde Filament, igual de directo que
     * Inventario::registrarMovimiento(). Los `CompraItem` no se editan ni se
     * borran después (igual que MovimientoInventario) — si algo se
     * registró mal, se corrige con un ajuste manual de inventario.
     *
     * @param  array<int, array{insumo_id: int, cantidad: string, costo_unitario: string}>  $items
     */
    public static function registrar(
        Sede $sede,
        Proveedor $proveedor,
        User $usuario,
        array $items,
        ?string $numeroFacturaProveedor = null,
        ?string $notas = null,
    ): self {
        if ($items === []) {
            throw new InvalidArgumentException('Una compra debe tener al menos un ítem.');
        }

        return DB::transaction(function () use ($sede, $proveedor, $usuario, $items, $numeroFacturaProveedor, $notas) {
            $compra = static::create([
                'empresa_id' => $sede->empresa_id,
                'sede_id' => $sede->id,
                'proveedor_id' => $proveedor->id,
                'usuario_id' => $usuario->id,
                'numero_factura_proveedor' => $numeroFacturaProveedor,
                'notas' => $notas,
            ]);

            foreach ($items as $item) {
                $insumo = Insumo::findOrFail($item['insumo_id']);

                $compraItem = CompraItem::create([
                    'compra_id' => $compra->id,
                    'insumo_id' => $insumo->id,
                    'cantidad' => $item['cantidad'],
                    'costo_unitario' => $item['costo_unitario'],
                ]);

                $inventario = Inventario::firstOrCreate(
                    ['sede_id' => $sede->id, 'insumo_id' => $insumo->id],
                    ['empresa_id' => $sede->empresa_id, 'cantidad_actual' => 0],
                );

                $inventario->increment('cantidad_actual', (float) $compraItem->cantidad);

                MovimientoInventario::create([
                    'empresa_id' => $sede->empresa_id,
                    'sede_id' => $sede->id,
                    'insumo_id' => $insumo->id,
                    'usuario_id' => $usuario->id,
                    'compra_id' => $compra->id,
                    'tipo' => TipoMovimientoInventario::Entrada,
                    'cantidad' => $compraItem->cantidad,
                    'motivo' => "Compra a {$proveedor->nombre} #{$compra->id}",
                ]);
            }

            return $compra;
        });
    }

    public function total(): string
    {
        return $this->items->reduce(fn (string $acumulado, CompraItem $item) => bcadd($acumulado, $item->subtotal(), 2), '0.00');
    }
}
