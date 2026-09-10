<?php

namespace App\Models;

use App\Enums\TipoMovimientoInventario;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

#[Fillable(['empresa_id', 'insumo_id', 'sede_origen_id', 'sede_destino_id', 'usuario_id', 'cantidad', 'notas'])]
class TrasladoInventario extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:3',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function insumo(): BelongsTo
    {
        return $this->belongsTo(Insumo::class);
    }

    public function sedeOrigen(): BelongsTo
    {
        return $this->belongsTo(Sede::class, 'sede_origen_id');
    }

    public function sedeDestino(): BelongsTo
    {
        return $this->belongsTo(Sede::class, 'sede_destino_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Mueve `cantidad` de un insumo de una sede a otra: descuenta el
     * inventario de origen, aumenta el de destino y deja un
     * `MovimientoInventario` en cada lado, todo en una sola transacción — es
     * el criterio de terminación de Fase 7 (ver docs/ROADMAP.md).
     *
     * Sin bloqueo por falta de stock en origen (mismo criterio que las
     * ventas — ver DEC-016): el stock de origen puede quedar negativo, solo
     * es responsabilidad de quien registra el traslado que sea correcto.
     */
    public static function realizar(
        Insumo $insumo,
        Sede $origen,
        Sede $destino,
        User $usuario,
        string $cantidad,
        ?string $notas = null,
    ): self {
        if ($origen->is($destino)) {
            throw new InvalidArgumentException('La sede de origen y la de destino no pueden ser la misma.');
        }

        if ($origen->empresa_id !== $insumo->empresa_id || $destino->empresa_id !== $insumo->empresa_id) {
            throw new InvalidArgumentException('El insumo y las sedes deben pertenecer a la misma empresa.');
        }

        return DB::transaction(function () use ($insumo, $origen, $destino, $usuario, $cantidad, $notas) {
            $inventarioOrigen = Inventario::firstOrCreate(
                ['sede_id' => $origen->id, 'insumo_id' => $insumo->id],
                ['empresa_id' => $insumo->empresa_id, 'cantidad_actual' => 0],
            );
            $inventarioDestino = Inventario::firstOrCreate(
                ['sede_id' => $destino->id, 'insumo_id' => $insumo->id],
                ['empresa_id' => $insumo->empresa_id, 'cantidad_actual' => 0],
            );

            // Bloquear ambas filas en un orden determinístico (por id
            // ascendente) antes de mutar — evita deadlocks si dos traslados
            // en direcciones opuestas entre las mismas sedes corren a la vez
            // (el riesgo de concurrencia explícito de esta fase).
            $enOrden = $inventarioOrigen->id < $inventarioDestino->id
                ? [$inventarioOrigen, $inventarioDestino]
                : [$inventarioDestino, $inventarioOrigen];

            foreach ($enOrden as $inventario) {
                Inventario::query()->whereKey($inventario->id)->lockForUpdate()->first();
            }

            $traslado = static::create([
                'empresa_id' => $insumo->empresa_id,
                'insumo_id' => $insumo->id,
                'sede_origen_id' => $origen->id,
                'sede_destino_id' => $destino->id,
                'usuario_id' => $usuario->id,
                'cantidad' => $cantidad,
                'notas' => $notas,
            ]);

            $inventarioOrigen->decrement('cantidad_actual', (float) $cantidad);
            $inventarioDestino->increment('cantidad_actual', (float) $cantidad);

            MovimientoInventario::create([
                'empresa_id' => $insumo->empresa_id,
                'sede_id' => $origen->id,
                'insumo_id' => $insumo->id,
                'usuario_id' => $usuario->id,
                'traslado_inventario_id' => $traslado->id,
                'tipo' => TipoMovimientoInventario::Salida,
                'cantidad' => $cantidad,
                'motivo' => "Traslado a {$destino->nombre} #{$traslado->id}",
            ]);

            MovimientoInventario::create([
                'empresa_id' => $insumo->empresa_id,
                'sede_id' => $destino->id,
                'insumo_id' => $insumo->id,
                'usuario_id' => $usuario->id,
                'traslado_inventario_id' => $traslado->id,
                'tipo' => TipoMovimientoInventario::Entrada,
                'cantidad' => $cantidad,
                'motivo' => "Traslado desde {$origen->nombre} #{$traslado->id}",
            ]);

            return $traslado;
        });
    }
}
