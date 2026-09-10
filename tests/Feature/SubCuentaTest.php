<?php

use App\Enums\MedioPago;
use App\Enums\Rol;
use App\Enums\TipoPedido;
use App\Models\AreaPreparacion;
use App\Models\Empresa;
use App\Models\ItemPedido;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Sede;
use App\Models\SubCuenta;
use App\Models\User;

function crearPedidoConDosItems(string $nombreEmpresa): array
{
    $empresa = Empresa::factory()->create(['nombre' => $nombreEmpresa]);
    $sede = Sede::factory()->for($empresa)->create();
    $area = AreaPreparacion::factory()->for($empresa)->for($sede)->create();

    $usuario = User::factory()->create();
    $usuario->accesos()->create(['empresa_id' => $empresa->id, 'sede_id' => $sede->id, 'rol' => Rol::Caja]);

    $pedido = Pedido::abrir($sede, $usuario, TipoPedido::Mostrador, null, "idem-subcuenta-{$nombreEmpresa}");

    $productoA = Producto::factory()->create(['empresa_id' => $empresa->id, 'precio' => '10.00']);
    $itemA = ItemPedido::create([
        'pedido_id' => $pedido->id,
        'producto_id' => $productoA->id,
        'area_preparacion_id' => $area->id,
        'nombre_producto' => $productoA->nombre,
        'precio_unitario' => $productoA->precio,
        'cantidad' => 1,
    ]);

    $productoB = Producto::factory()->create(['empresa_id' => $empresa->id, 'precio' => '20.00']);
    $itemB = ItemPedido::create([
        'pedido_id' => $pedido->id,
        'producto_id' => $productoB->id,
        'area_preparacion_id' => $area->id,
        'nombre_producto' => $productoB->nombre,
        'precio_unitario' => $productoB->precio,
        'cantidad' => 1,
    ]);

    return [$empresa, $sede, $usuario, $pedido, $itemA, $itemB];
}

it('SubCuenta::crear() asigna ítems completos y calcula el total correctamente', function () {
    [, , $usuario, $pedido, $itemA, $itemB] = crearPedidoConDosItems('Empresa SubCuenta Basica');

    $subCuenta = SubCuenta::crear($pedido, 'Juan', [
        ['item_pedido_id' => $itemA->id, 'porcentaje' => 100],
    ], $usuario);

    expect($subCuenta->nombre)->toBe('Juan');
    expect($subCuenta->total())->toBe('10.00');
    expect($subCuenta->saldoPendiente())->toBe('10.00');

    $otra = SubCuenta::crear($pedido, 'María', [
        ['item_pedido_id' => $itemB->id, 'porcentaje' => 100],
    ], $usuario);

    expect($otra->total())->toBe('20.00');
});

it('SubCuenta::crear() permite repartir un mismo ítem por porcentaje entre varias sub-cuentas', function () {
    [, , $usuario, $pedido, $itemA] = crearPedidoConDosItems('Empresa SubCuenta Reparto');

    $mitad1 = SubCuenta::crear($pedido, 'Persona 1', [
        ['item_pedido_id' => $itemA->id, 'porcentaje' => 50],
    ], $usuario);

    $mitad2 = SubCuenta::crear($pedido, 'Persona 2', [
        ['item_pedido_id' => $itemA->id, 'porcentaje' => 50],
    ], $usuario);

    expect($mitad1->total())->toBe('5.00');
    expect($mitad2->total())->toBe('5.00');
});

it('SubCuenta::crear() rechaza asignar más del 100% de un mismo ítem entre sub-cuentas', function () {
    [, , $usuario, $pedido, $itemA] = crearPedidoConDosItems('Empresa SubCuenta Excede');

    SubCuenta::crear($pedido, 'Persona 1', [
        ['item_pedido_id' => $itemA->id, 'porcentaje' => 70],
    ], $usuario);

    SubCuenta::crear($pedido, 'Persona 2', [
        ['item_pedido_id' => $itemA->id, 'porcentaje' => 40],
    ], $usuario);
})->throws(InvalidArgumentException::class);

it('SubCuenta::crear() rechaza crear sobre un pedido que ya no está abierto', function () {
    [, , $usuario, $pedido, $itemA] = crearPedidoConDosItems('Empresa SubCuenta Pedido Cerrado');
    $pedido->anular();

    SubCuenta::crear($pedido, 'Persona 1', [
        ['item_pedido_id' => $itemA->id, 'porcentaje' => 100],
    ], $usuario);
})->throws(InvalidArgumentException::class);

it('SubCuenta::crear() rechaza una sub-cuenta sin ítems asignados', function () {
    [, , $usuario, $pedido] = crearPedidoConDosItems('Empresa SubCuenta Vacia');

    SubCuenta::crear($pedido, 'Persona 1', [], $usuario);
})->throws(InvalidArgumentException::class);

it('un pago registrado contra una sub-cuenta queda etiquetado y cuenta para el total del pedido', function () {
    [, , $usuario, $pedido, $itemA, $itemB] = crearPedidoConDosItems('Empresa SubCuenta Pago');

    $subCuentaA = SubCuenta::crear($pedido, 'Juan', [['item_pedido_id' => $itemA->id, 'porcentaje' => 100]], $usuario);
    $subCuentaB = SubCuenta::crear($pedido, 'María', [['item_pedido_id' => $itemB->id, 'porcentaje' => 100]], $usuario);

    Pago::registrar($pedido, $usuario, MedioPago::Efectivo, '10.00', 'idem-pago-subcuenta-a', $subCuentaA);

    expect($subCuentaA->fresh()->totalPagado())->toBe('10.00');
    expect($subCuentaA->fresh()->saldoPendiente())->toBe('0.00');
    expect($subCuentaB->fresh()->totalPagado())->toBe('0.00');
    expect($pedido->fresh()->estaAbierto())->toBeTrue(); // falta pagar la sub-cuenta de María

    Pago::registrar($pedido, $usuario, MedioPago::Tarjeta, '20.00', 'idem-pago-subcuenta-b', $subCuentaB);

    expect($pedido->fresh()->estado->value)->toBe('cobrado'); // ambas sub-cuentas cubren el total del pedido
});

it('eliminar() borra una sub-cuenta sin pagos pero rechaza una que ya tiene pagos', function () {
    [, , $usuario, $pedido, $itemA, $itemB] = crearPedidoConDosItems('Empresa SubCuenta Eliminar');

    $sinPagos = SubCuenta::crear($pedido, 'Sin pagos', [['item_pedido_id' => $itemA->id, 'porcentaje' => 100]], $usuario);
    $sinPagos->eliminar();
    expect(SubCuenta::find($sinPagos->id))->toBeNull();

    $conPago = SubCuenta::crear($pedido, 'Con pago', [['item_pedido_id' => $itemB->id, 'porcentaje' => 100]], $usuario);
    Pago::registrar($pedido, $usuario, MedioPago::Efectivo, '5.00', 'idem-pago-parcial-eliminar', $conPago);

    $conPago->eliminar();
})->throws(RuntimeException::class);

it('la API crea, lista y cobra una sub-cuenta correctamente', function () {
    [, , $usuario, $pedido, $itemA] = crearPedidoConDosItems('Empresa API SubCuenta');

    $this->actingAs($usuario);

    $creada = $this->postJson("/api/pos/pedidos/{$pedido->id}/sub-cuentas", [
        'nombre' => 'Juan',
        'asignaciones' => [['item_pedido_id' => $itemA->id, 'porcentaje' => 100]],
    ])->assertOk()->json('data');

    expect($creada['total'])->toBe('10.00');

    $this->getJson("/api/pos/pedidos/{$pedido->id}/sub-cuentas")
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->postJson("/api/pos/sub-cuentas/{$creada['id']}/pagos", [
        'medio' => 'efectivo',
        'monto' => '10.00',
        'idempotency_key' => 'idem-api-pago-subcuenta',
    ])->assertOk()->assertJsonPath('data.saldo_pendiente', '0.00');
});

it('la API rechaza operar sobre una sub-cuenta de un pedido en otra sede sin acceso', function () {
    [, , , $pedido, $itemA] = crearPedidoConDosItems('Empresa API SubCuenta Sin Acceso');
    $sinAcceso = User::factory()->create();

    $this->actingAs($sinAcceso)
        ->postJson("/api/pos/pedidos/{$pedido->id}/sub-cuentas", [
            'nombre' => 'Juan',
            'asignaciones' => [['item_pedido_id' => $itemA->id, 'porcentaje' => 100]],
        ])
        ->assertForbidden();
});
