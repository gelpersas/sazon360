<?php

namespace App\Models;

use App\Enums\TipoMovimientoInventario;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

#[Fillable(['empresa_id', 'sede_id', 'insumo_id', 'cantidad_actual'])]
class Inventario extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'cantidad_actual' => 'decimal:3',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function insumo(): BelongsTo
    {
        return $this->belongsTo(Insumo::class);
    }

    public function bajoMinimo(): bool
    {
        return bccomp((string) $this->cantidad_actual, (string) $this->insumo->stock_minimo, 3) < 0;
    }

    /**
     * Movimiento manual (entrada/salida/ajuste — ver docs/DECISIONES.md
     * DEC-017). El descuento automático por venta NO pasa por aquí — ver
     * Pedido::descontarInventario(), que decrementa directo con decrement()
     * dentro de la transacción del pago.
     */
    public static function registrarMovimiento(
        Sede $sede,
        Insumo $insumo,
        User $usuario,
        TipoMovimientoInventario $tipo,
        string $cantidad,
        string $motivo,
    ): MovimientoInventario {
        return DB::transaction(function () use ($sede, $insumo, $usuario, $tipo, $cantidad, $motivo) {
            $inventario = static::firstOrCreate(
                ['sede_id' => $sede->id, 'insumo_id' => $insumo->id],
                ['empresa_id' => $sede->empresa_id, 'cantidad_actual' => 0],
            );

            match (true) {
                $tipo === TipoMovimientoInventario::Entrada => $inventario->increment('cantidad_actual', (float) $cantidad),
                $tipo->resta() => $inventario->decrement('cantidad_actual', (float) $cantidad),
                default => $inventario->update(['cantidad_actual' => $cantidad]), // Ajuste
            };

            return MovimientoInventario::create([
                'empresa_id' => $sede->empresa_id,
                'sede_id' => $sede->id,
                'insumo_id' => $insumo->id,
                'usuario_id' => $usuario->id,
                'tipo' => $tipo,
                'cantidad' => $cantidad,
                'motivo' => $motivo,
            ]);
        });
    }
}
