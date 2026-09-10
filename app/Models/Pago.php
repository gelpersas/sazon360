<?php

namespace App\Models;

use App\Enums\EstadoPedido;
use App\Enums\MedioPago;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

#[Fillable(['empresa_id', 'pedido_id', 'sub_cuenta_id', 'usuario_id', 'medio', 'monto', 'idempotency_key'])]
class Pago extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'medio' => MedioPago::class,
            'monto' => 'decimal:2',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function subCuenta(): BelongsTo
    {
        return $this->belongsTo(SubCuenta::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Registra un pago (parcial o total, permite medios mixtos — ver
     * docs/REGLAS-NEGOCIO.md "Pagos"). Idempotente por `idempotencyKey`: un
     * reintento por corte de red con la misma clave devuelve el pago ya
     * creado en vez de cobrar dos veces. Si con este pago el total queda
     * cubierto, el pedido pasa a `cobrado` dentro de la misma transacción.
     *
     * `$subCuenta` es solo una etiqueta (Fase 10, "por productos"/"por
     * personas") — el pago sigue siendo un pago normal contra `$pedido`, no
     * cambia en nada cuándo el pedido queda cobrado ni el descuento de
     * inventario/factura, que siguen dependiendo del total completo del
     * pedido, no de la sub-cuenta.
     */
    public static function registrar(Pedido $pedido, User $usuario, MedioPago $medio, string $monto, string $idempotencyKey, ?SubCuenta $subCuenta = null): self
    {
        $existente = static::where('idempotency_key', $idempotencyKey)->first();

        if ($existente) {
            return $existente;
        }

        try {
            return DB::transaction(function () use ($pedido, $usuario, $medio, $monto, $idempotencyKey, $subCuenta) {
                $pedidoBloqueado = Pedido::query()->lockForUpdate()->findOrFail($pedido->id);

                if ($pedidoBloqueado->estado !== EstadoPedido::Abierto) {
                    throw new RuntimeException('Este pedido no está abierto — no se le pueden registrar pagos.');
                }

                $pago = static::create([
                    'empresa_id' => $pedidoBloqueado->empresa_id,
                    'pedido_id' => $pedidoBloqueado->id,
                    'sub_cuenta_id' => $subCuenta?->id,
                    'usuario_id' => $usuario->id,
                    'medio' => $medio,
                    'monto' => $monto,
                    'idempotency_key' => $idempotencyKey,
                ]);

                $totalPagado = $pedidoBloqueado->pagos()->sum('monto');

                if (bccomp($totalPagado, $pedidoBloqueado->total(), 2) >= 0) {
                    $pedidoBloqueado->update([
                        'estado' => EstadoPedido::Cobrado,
                        'cerrado_at' => now(),
                    ]);

                    // Descuento de inventario por receta — ver
                    // docs/DECISIONES.md DEC-014: ocurre una sola vez, aquí,
                    // en el momento exacto en que el pedido queda cobrado.
                    $pedidoBloqueado->descontarInventario($usuario);

                    // Emisión del comprobante fiscal — ver DEC-018/DEC-019.
                    // Solo crea la fila `pendiente` y encola el Job (que se
                    // procesa después de confirmada esta transacción); un
                    // fallo del proveedor de facturación nunca revierte el
                    // cobro ya registrado.
                    FacturaElectronica::emitirPara($pedidoBloqueado);
                }

                return $pago;
            });
        } catch (QueryException $e) {
            // Carrera: dos requests con la misma clave llegaron casi juntos
            // y ambas pasaron el chequeo inicial — la constraint única
            // resuelve cuál gana; la que pierde recupera el pago ya creado.
            $pago = static::where('idempotency_key', $idempotencyKey)->first();

            if ($pago) {
                return $pago;
            }

            throw $e;
        }
    }
}
