<?php

namespace App\Http\Controllers\Pos;

use App\Enums\MedioPago;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\StorePagoRequest;
use App\Http\Requests\Pos\StoreSubCuentaRequest;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\SubCuenta;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;

/**
 * "Por productos"/"por personas" (Fase 10, ver docs/DECISIONES.md) — mismo
 * mecanismo para ambas, la diferencia es solo el nombre libre que el
 * mesero le pone a cada sub-cuenta. "Por mesa original" no necesita nada de
 * esto: ya funciona pagando cada `Pedido` del grupo por separado (Fase 9).
 *
 * Sin Policy propia — mismo criterio que Pedido/Comanda/Pago/GrupoMesa (ver
 * el docblock de PedidoPolicy): `rolEnSede($sede)` inline en cada método en
 * vez de una clase de Policy casi idéntica. Revisado explícitamente en la
 * auditoría de 2026-09-04/07 (ver AUDITORIA.md) — patrón deliberado, no un
 * descuido.
 */
class SubCuentaController extends Controller
{
    public function index(Request $request, Pedido $pedido): array
    {
        abort_unless($request->user()->accedeAMostradorEnSede($pedido->sede), 403);

        return ['data' => $pedido->subCuentas()->get()->map(fn (SubCuenta $sub) => $this->serializar($sub))->values()];
    }

    public function store(StoreSubCuentaRequest $request, Pedido $pedido): array
    {
        abort_unless($request->user()->accedeAMostradorEnSede($pedido->sede), 403);

        try {
            $subCuenta = SubCuenta::crear(
                pedido: $pedido,
                nombre: $request->validated('nombre'),
                asignaciones: $request->validated('asignaciones'),
                usuario: $request->user(),
            );
        } catch (InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }

        return ['data' => $this->serializar($subCuenta)];
    }

    public function destroy(Request $request, SubCuenta $subCuenta): array
    {
        abort_unless($request->user()->accedeAMostradorEnSede($subCuenta->pedido->sede), 403);

        try {
            $subCuenta->eliminar();
        } catch (RuntimeException $e) {
            abort(422, $e->getMessage());
        }

        return ['ok' => true];
    }

    public function pagar(StorePagoRequest $request, SubCuenta $subCuenta): array
    {
        abort_unless($request->user()->accedeACajaEnSede($subCuenta->pedido->sede), 403);

        try {
            Pago::registrar(
                pedido: $subCuenta->pedido,
                usuario: $request->user(),
                medio: MedioPago::from($request->validated('medio')),
                monto: (string) $request->validated('monto'),
                idempotencyKey: $request->validated('idempotency_key'),
                subCuenta: $subCuenta,
            );
        } catch (RuntimeException $e) {
            abort(422, $e->getMessage());
        }

        return ['data' => $this->serializar($subCuenta->fresh())];
    }

    private function serializar(SubCuenta $subCuenta): array
    {
        $subCuenta->loadMissing('items.itemPedido');

        return [
            'id' => $subCuenta->id,
            'pedido_id' => $subCuenta->pedido_id,
            'nombre' => $subCuenta->nombre,
            'total' => $subCuenta->total(),
            'total_pagado' => $subCuenta->totalPagado(),
            'saldo_pendiente' => $subCuenta->saldoPendiente(),
            'items' => $subCuenta->items->map(fn ($subItem) => [
                'item_pedido_id' => $subItem->item_pedido_id,
                'nombre_producto' => $subItem->itemPedido->nombre_producto,
                'porcentaje' => (string) $subItem->porcentaje,
                'monto' => $subItem->monto(),
            ])->values(),
        ];
    }
}
