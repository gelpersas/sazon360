<?php

namespace App\Http\Controllers\Pos;

use App\Enums\MedioPago;
use App\Enums\TipoPedido;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\StoreItemPedidoRequest;
use App\Http\Requests\Pos\StorePagoRequest;
use App\Http\Requests\Pos\StorePedidoRequest;
use App\Models\Cliente;
use App\Models\Comanda;
use App\Models\ItemPedido;
use App\Models\Mesa;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Sede;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;

class PedidoController extends Controller
{
    public function index(Request $request, Sede $sede): array
    {
        abort_unless($request->user()->accedeAMostradorEnSede($sede), 403);

        $pedidos = $sede->pedidos()
            ->where('estado', 'abierto')
            ->with('mesa')
            ->latest()
            ->get();

        return ['data' => $pedidos->map(fn (Pedido $pedido) => $this->serializarResumen($pedido))];
    }

    public function store(StorePedidoRequest $request, Sede $sede): array
    {
        abort_unless($request->user()->accedeAMostradorEnSede($sede), 403);

        $tipo = TipoPedido::from($request->validated('tipo'));
        $mesa = $request->validated('mesa_id') ? Mesa::where('sede_id', $sede->id)->findOrFail($request->validated('mesa_id')) : null;

        try {
            $pedido = Pedido::abrir(
                sede: $sede,
                usuario: $request->user(),
                tipo: $tipo,
                mesa: $mesa,
                idempotencyKey: $request->validated('idempotency_key'),
                notas: $request->validated('notas'),
            );
        } catch (InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }

        return ['data' => $this->serializar($pedido)];
    }

    public function show(Request $request, Pedido $pedido): array
    {
        abort_unless($request->user()->accedeAMostradorEnSede($pedido->sede), 403);

        return ['data' => $this->serializar($pedido)];
    }

    public function agregarItem(StoreItemPedidoRequest $request, Pedido $pedido): array
    {
        abort_unless($request->user()->accedeAMostradorEnSede($pedido->sede), 403);

        $producto = Producto::where('empresa_id', $pedido->empresa_id)->findOrFail($request->validated('producto_id'));
        $area = $pedido->sede->areasPreparacion()->findOrFail($request->validated('area_preparacion_id'));

        try {
            $item = ItemPedido::create([
                'pedido_id' => $pedido->id,
                'producto_id' => $producto->id,
                'area_preparacion_id' => $area->id,
                'nombre_producto' => $producto->nombre,
                'precio_unitario' => $producto->precio,
                'cantidad' => $request->validated('cantidad'),
                'notas' => $request->validated('notas'),
            ]);
        } catch (InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }

        return ['data' => $this->serializar($pedido->fresh())];
    }

    public function quitarItem(Request $request, Pedido $pedido, ItemPedido $item): array
    {
        abort_unless($request->user()->accedeAMostradorEnSede($pedido->sede), 403);
        abort_unless($item->pedido_id === $pedido->id, 404);

        try {
            $item->delete();
        } catch (RuntimeException $e) {
            abort(422, $e->getMessage());
        }

        return ['data' => $this->serializar($pedido->fresh())];
    }

    public function actualizarItem(Request $request, Pedido $pedido, ItemPedido $item): array
    {
        abort_unless($request->user()->accedeAMostradorEnSede($pedido->sede), 403);
        abort_unless($item->pedido_id === $pedido->id, 404);

        $validated = $request->validate([
            'cantidad' => ['sometimes', 'integer', 'min:1'],
            'notas' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        abort_if($validated === [], 422, 'Nada que actualizar.');

        try {
            if (array_key_exists('cantidad', $validated)) {
                $item->actualizarCantidad($validated['cantidad']);
            }

            if (array_key_exists('notas', $validated)) {
                $item->actualizarNotas($validated['notas']);
            }
        } catch (RuntimeException $e) {
            abort(422, $e->getMessage());
        }

        return ['data' => $this->serializar($pedido->fresh())];
    }

    public function enviarComanda(Request $request, Pedido $pedido): array
    {
        abort_unless($request->user()->accedeAMostradorEnSede($pedido->sede), 403);

        $comandas = $pedido->enviarComanda();

        return [
            'data' => $this->serializar($pedido->fresh()),
            'comandas_creadas' => $comandas->map(fn (Comanda $comanda) => $comanda->id)->values(),
        ];
    }

    /**
     * Asignación opcional "al cobrar" — mismo dominio que pagar() (ver
     * Rol::accedeACaja()), no el de tomar el pedido.
     */
    public function asignarCliente(Request $request, Pedido $pedido): array
    {
        abort_unless($request->user()->accedeACajaEnSede($pedido->sede), 403);

        $data = $request->validate([
            'cliente_id' => ['nullable', 'integer'],
        ]);

        $cliente = null;

        if ($data['cliente_id'] ?? null) {
            $cliente = Cliente::where('empresa_id', $pedido->empresa_id)->findOrFail($data['cliente_id']);
        }

        try {
            $pedido->asignarCliente($cliente);
        } catch (RuntimeException $e) {
            abort(422, $e->getMessage());
        }

        return ['data' => $this->serializar($pedido->fresh())];
    }

    public function pagar(StorePagoRequest $request, Pedido $pedido): array
    {
        abort_unless($request->user()->accedeACajaEnSede($pedido->sede), 403);

        try {
            Pago::registrar(
                pedido: $pedido,
                usuario: $request->user(),
                medio: MedioPago::from($request->validated('medio')),
                monto: (string) $request->validated('monto'),
                idempotencyKey: $request->validated('idempotency_key'),
            );
        } catch (RuntimeException $e) {
            abort(422, $e->getMessage());
        }

        return ['data' => $this->serializar($pedido->fresh())];
    }

    public function anular(Request $request, Pedido $pedido): array
    {
        abort_unless($request->user()->accedeAMostradorEnSede($pedido->sede), 403);

        try {
            $pedido->anular();
        } catch (RuntimeException $e) {
            abort(422, $e->getMessage());
        }

        return ['data' => $this->serializar($pedido)];
    }

    private function serializarResumen(Pedido $pedido): array
    {
        return [
            'id' => $pedido->id,
            'tipo' => $pedido->tipo->value,
            'estado' => $pedido->estado->value,
            'mesa' => $pedido->mesa?->only(['id', 'nombre', 'grupo_mesa_id']),
            'total' => $pedido->total(),
            'created_at' => $pedido->created_at->toIso8601String(),
        ];
    }

    private function serializar(Pedido $pedido): array
    {
        $pedido->loadMissing(['items.comanda', 'comandas.areaPreparacion', 'pagos', 'mesa', 'subCuentas', 'cliente']);

        return [
            'id' => $pedido->id,
            'tipo' => $pedido->tipo->value,
            'estado' => $pedido->estado->value,
            'mesa' => $pedido->mesa?->only(['id', 'nombre', 'grupo_mesa_id']),
            'cliente' => $pedido->cliente ? [
                'id' => $pedido->cliente->id,
                'nombre' => $pedido->cliente->nombre_completo,
                'tipo_documento' => $pedido->cliente->tipo_documento->value,
                'numero_documento' => $pedido->cliente->numero_documento,
            ] : null,
            'notas' => $pedido->notas,
            'total' => $pedido->total(),
            'total_pagado' => $pedido->totalPagado(),
            'saldo_pendiente' => $pedido->saldoPendiente(),
            'items' => $pedido->items->map(fn (ItemPedido $item) => [
                'id' => $item->id,
                'producto_id' => $item->producto_id,
                'nombre_producto' => $item->nombre_producto,
                'precio_unitario' => (string) $item->precio_unitario,
                'cantidad' => $item->cantidad,
                'subtotal' => $item->subtotal(),
                'notas' => $item->notas,
                'comanda_id' => $item->comanda_id,
                'enviado' => $item->comanda_id !== null,
            ])->values(),
            'comandas' => $pedido->comandas->map(fn (Comanda $comanda) => [
                'id' => $comanda->id,
                'area' => $comanda->areaPreparacion->nombre,
                'estado' => $comanda->estado->value,
            ])->values(),
            'pagos' => $pedido->pagos->map(fn (Pago $pago) => [
                'id' => $pago->id,
                'medio' => $pago->medio->value,
                'monto' => (string) $pago->monto,
                'sub_cuenta_id' => $pago->sub_cuenta_id,
            ])->values(),
            // Solo un resumen liviano — el detalle (ítems asignados, montos)
            // se consulta vía GET /pedidos/{pedido}/sub-cuentas.
            'sub_cuentas' => $pedido->subCuentas->map(fn ($sub) => [
                'id' => $sub->id,
                'nombre' => $sub->nombre,
            ])->values(),
        ];
    }
}
