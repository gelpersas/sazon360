<?php

use App\Enums\EstadoComanda;
use App\Enums\Rol;
use App\Events\ComandaActualizada;
use App\Models\AreaPreparacion;
use App\Models\Categoria;
use App\Models\Comanda;
use App\Models\Empresa;
use App\Models\ItemPedido;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Sede;
use App\Models\User;
use Illuminate\Support\Facades\Event;

function crearSedeConCatalogoYUsuarioCaja(string $nombreEmpresa): array
{
    $empresa = Empresa::factory()->create(['nombre' => $nombreEmpresa]);
    $sede = Sede::factory()->for($empresa)->create();
    $area = AreaPreparacion::factory()->for($empresa)->for($sede)->create();
    $mesa = Mesa::factory()->for($empresa)->for($sede)->create();
    $categoria = Categoria::factory()->for($empresa)->create();
    $producto = Producto::factory()->for($empresa)->for($categoria)->create(['precio' => '10.00']);

    $cajero = User::factory()->create();
    $cajero->accesos()->create([
        'empresa_id' => $empresa->id,
        'sede_id' => $sede->id,
        'rol' => Rol::Caja,
    ]);

    return [$empresa, $sede, $area, $mesa, $producto, $cajero];
}

function crearCocineroEnSede(Empresa $empresa, Sede $sede): User
{
    $cocinero = User::factory()->create();
    $cocinero->accesos()->create([
        'empresa_id' => $empresa->id,
        'sede_id' => $sede->id,
        'rol' => Rol::AreaPreparacion,
    ]);

    return $cocinero;
}

it('un usuario con credenciales válidas puede iniciar sesión en el POS y consultar /me', function () {
    [, , , , , $cajero] = crearSedeConCatalogoYUsuarioCaja('Empresa Login POS');

    // Sanctum solo activa la sesión (stateful) en requests que parecen venir
    // de un frontend de primera parte — el cliente de test no manda Referer
    // por defecto, así que hay que simularlo (ver docs/DECISIONES.md DEC-011).
    $login = $this->withHeader('Referer', 'http://localhost')->postJson('/api/pos/login', [
        'email' => $cajero->email,
        'password' => 'password',
    ]);

    $login->assertOk();

    $this->withHeader('Referer', 'http://localhost')
        ->getJson('/api/pos/me')
        ->assertOk()
        ->assertJsonPath('email', $cajero->email);
});

it('rechaza credenciales inválidas', function () {
    [, , , , , $cajero] = crearSedeConCatalogoYUsuarioCaja('Empresa Login Malo');

    $this->postJson('/api/pos/login', [
        'email' => $cajero->email,
        'password' => 'incorrecta',
    ])->assertUnprocessable();
});

it('un usuario no autenticado no puede acceder a rutas protegidas del POS', function () {
    [, $sede] = crearSedeConCatalogoYUsuarioCaja('Empresa Sin Auth');

    $this->getJson("/api/pos/sedes/{$sede->id}/pedidos")->assertUnauthorized();
});

it('flujo completo: crear pedido, agregar ítems, enviar comanda, avanzar en el KDS y cobrar', function () {
    [$empresa, $sede, $area, $mesa, $producto, $cajero] = crearSedeConCatalogoYUsuarioCaja('Empresa Flujo Completo');
    $cocinero = crearCocineroEnSede($empresa, $sede);

    $this->actingAs($cajero);

    $pedido = $this->postJson("/api/pos/sedes/{$sede->id}/pedidos", [
        'tipo' => 'mesa',
        'mesa_id' => $mesa->id,
        'idempotency_key' => 'pedido-flujo-1',
    ])->assertOk()->json('data');

    $this->postJson("/api/pos/pedidos/{$pedido['id']}/items", [
        'producto_id' => $producto->id,
        'area_preparacion_id' => $area->id,
        'cantidad' => 2,
    ])->assertOk()->assertJsonPath('data.total', '20.00');

    $envio = $this->postJson("/api/pos/pedidos/{$pedido['id']}/enviar-comanda")->assertOk();
    $comandaId = $envio->json('comandas_creadas.0');

    expect($comandaId)->not->toBeNull();

    // Cocina (KDS) es dominio del rol Área de preparación, no de Caja — ver
    // App\Enums\Rol::accedeACocina().
    $this->actingAs($cocinero);

    $this->getJson("/api/pos/sedes/{$sede->id}/comandas")
        ->assertOk()
        ->assertJsonPath('data.0.id', $comandaId)
        ->assertJsonPath('data.0.estado', 'pendiente');

    $this->postJson("/api/pos/comandas/{$comandaId}/avanzar")->assertOk()->assertJsonPath('data.estado', 'en_preparacion');
    $this->postJson("/api/pos/comandas/{$comandaId}/avanzar")->assertOk()->assertJsonPath('data.estado', 'lista');

    // El filtro por defecto del listado debe seguir mostrando la comanda una
    // vez que queda "lista" — si no, el botón "Entregar" del KDS sería
    // inalcanzable (bug real de Fase 4, corregido en Fase 8).
    $this->getJson("/api/pos/sedes/{$sede->id}/comandas")
        ->assertOk()
        ->assertJsonPath('data.0.id', $comandaId)
        ->assertJsonPath('data.0.estado', 'lista');

    // Cobrar es dominio del rol Caja, no de cocina — de vuelta al cajero.
    $this->actingAs($cajero);

    $cobro = $this->postJson("/api/pos/pedidos/{$pedido['id']}/pagos", [
        'medio' => 'efectivo',
        'monto' => '20.00',
        'idempotency_key' => 'pago-flujo-1',
    ])->assertOk();

    expect($cobro->json('data.estado'))->toBe('cobrado');
    expect($cobro->json('data.saldo_pendiente'))->toBe('0.00');
});

it('crear el mismo pedido dos veces con la misma clave de idempotencia no lo duplica', function () {
    [, $sede, , , , $cajero] = crearSedeConCatalogoYUsuarioCaja('Empresa Idempotencia Pedido');

    $this->actingAs($cajero);

    $payload = ['tipo' => 'mostrador', 'idempotency_key' => 'clave-repetida'];

    $r1 = $this->postJson("/api/pos/sedes/{$sede->id}/pedidos", $payload)->assertOk();
    $r2 = $this->postJson("/api/pos/sedes/{$sede->id}/pedidos", $payload)->assertOk();

    expect($r1->json('data.id'))->toBe($r2->json('data.id'));
    expect(Pedido::where('idempotency_key', 'clave-repetida')->count())->toBe(1);
});

it('pagar dos veces con la misma clave de idempotencia no duplica el pago ni cobra dos veces', function () {
    [, $sede, $area, , $producto, $cajero] = crearSedeConCatalogoYUsuarioCaja('Empresa Idempotencia Pago');

    $this->actingAs($cajero);

    $pedido = $this->postJson("/api/pos/sedes/{$sede->id}/pedidos", [
        'tipo' => 'mostrador',
        'idempotency_key' => 'pedido-pago-idem',
    ])->json('data');

    $this->postJson("/api/pos/pedidos/{$pedido['id']}/items", [
        'producto_id' => $producto->id,
        'area_preparacion_id' => $area->id,
        'cantidad' => 1,
    ]);

    $payload = ['medio' => 'efectivo', 'monto' => '10.00', 'idempotency_key' => 'clave-pago-repetida'];

    $this->postJson("/api/pos/pedidos/{$pedido['id']}/pagos", $payload)->assertOk();
    $this->postJson("/api/pos/pedidos/{$pedido['id']}/pagos", $payload)->assertOk();

    $pedidoFinal = Pedido::find($pedido['id']);

    expect($pedidoFinal->pagos()->count())->toBe(1);
    expect($pedidoFinal->totalPagado())->toBe('10.00');
});

it('un usuario sin acceso a la sede no puede crear pedidos ahí', function () {
    [, $sedeA] = crearSedeConCatalogoYUsuarioCaja('Empresa Aislamiento A');
    [, , , , , $cajeroB] = crearSedeConCatalogoYUsuarioCaja('Empresa Aislamiento B');

    $this->actingAs($cajeroB);

    $this->postJson("/api/pos/sedes/{$sedeA->id}/pedidos", [
        'tipo' => 'mostrador',
        'idempotency_key' => 'intento-cruzado',
    ])->assertForbidden();
});

it('no se puede quitar un ítem que ya fue enviado a cocina', function () {
    [, $sede, $area, , $producto, $cajero] = crearSedeConCatalogoYUsuarioCaja('Empresa Item Enviado');

    $this->actingAs($cajero);

    $pedido = $this->postJson("/api/pos/sedes/{$sede->id}/pedidos", [
        'tipo' => 'mostrador',
        'idempotency_key' => 'pedido-item-enviado',
    ])->json('data');

    $item = $this->postJson("/api/pos/pedidos/{$pedido['id']}/items", [
        'producto_id' => $producto->id,
        'area_preparacion_id' => $area->id,
        'cantidad' => 1,
    ])->json('data.items.0');

    $this->postJson("/api/pos/pedidos/{$pedido['id']}/enviar-comanda")->assertOk();

    $this->deleteJson("/api/pos/pedidos/{$pedido['id']}/items/{$item['id']}")->assertUnprocessable();

    expect(ItemPedido::find($item['id']))->not->toBeNull();
});

it('actualiza la cantidad de un ítem no enviado y recalcula el total del pedido', function () {
    [, $sede, $area, , $producto, $cajero] = crearSedeConCatalogoYUsuarioCaja('Empresa Cantidad Item');

    $this->actingAs($cajero);

    $pedido = $this->postJson("/api/pos/sedes/{$sede->id}/pedidos", [
        'tipo' => 'mostrador',
        'idempotency_key' => 'pedido-cantidad-1',
    ])->json('data');

    $item = $this->postJson("/api/pos/pedidos/{$pedido['id']}/items", [
        'producto_id' => $producto->id,
        'area_preparacion_id' => $area->id,
        'cantidad' => 1,
    ])->json('data.items.0');

    $this->patchJson("/api/pos/pedidos/{$pedido['id']}/items/{$item['id']}", ['cantidad' => 3])
        ->assertOk()
        ->assertJsonPath('data.total', '30.00');

    expect(ItemPedido::find($item['id'])->cantidad)->toBe(3);
});

it('rechaza actualizar la cantidad de un ítem a menos de 1', function () {
    [, $sede, $area, , $producto, $cajero] = crearSedeConCatalogoYUsuarioCaja('Empresa Cantidad Invalida');

    $this->actingAs($cajero);

    $pedido = $this->postJson("/api/pos/sedes/{$sede->id}/pedidos", [
        'tipo' => 'mostrador',
        'idempotency_key' => 'pedido-cantidad-2',
    ])->json('data');

    $item = $this->postJson("/api/pos/pedidos/{$pedido['id']}/items", [
        'producto_id' => $producto->id,
        'area_preparacion_id' => $area->id,
        'cantidad' => 1,
    ])->json('data.items.0');

    $this->patchJson("/api/pos/pedidos/{$pedido['id']}/items/{$item['id']}", ['cantidad' => 0])
        ->assertUnprocessable();
});

it('actualiza la nota de un ítem no enviado', function () {
    [, $sede, $area, , $producto, $cajero] = crearSedeConCatalogoYUsuarioCaja('Empresa Nota Item');

    $this->actingAs($cajero);

    $pedido = $this->postJson("/api/pos/sedes/{$sede->id}/pedidos", [
        'tipo' => 'mostrador',
        'idempotency_key' => 'pedido-nota-1',
    ])->json('data');

    $item = $this->postJson("/api/pos/pedidos/{$pedido['id']}/items", [
        'producto_id' => $producto->id,
        'area_preparacion_id' => $area->id,
        'cantidad' => 1,
    ])->json('data.items.0');

    $this->patchJson("/api/pos/pedidos/{$pedido['id']}/items/{$item['id']}", ['notas' => 'Sin azúcar'])
        ->assertOk()
        ->assertJsonPath('data.items.0.notas', 'Sin azúcar');

    expect(ItemPedido::find($item['id'])->notas)->toBe('Sin azúcar');
});

it('rechaza actualizar un ítem sin mandar ni cantidad ni notas', function () {
    [, $sede, $area, , $producto, $cajero] = crearSedeConCatalogoYUsuarioCaja('Empresa Actualizar Vacio');

    $this->actingAs($cajero);

    $pedido = $this->postJson("/api/pos/sedes/{$sede->id}/pedidos", [
        'tipo' => 'mostrador',
        'idempotency_key' => 'pedido-nota-2',
    ])->json('data');

    $item = $this->postJson("/api/pos/pedidos/{$pedido['id']}/items", [
        'producto_id' => $producto->id,
        'area_preparacion_id' => $area->id,
        'cantidad' => 1,
    ])->json('data.items.0');

    $this->patchJson("/api/pos/pedidos/{$pedido['id']}/items/{$item['id']}", [])
        ->assertUnprocessable();
});

it('no se puede cambiar la cantidad de un ítem que ya fue enviado a cocina', function () {
    [, $sede, $area, , $producto, $cajero] = crearSedeConCatalogoYUsuarioCaja('Empresa Cantidad Enviado');

    $this->actingAs($cajero);

    $pedido = $this->postJson("/api/pos/sedes/{$sede->id}/pedidos", [
        'tipo' => 'mostrador',
        'idempotency_key' => 'pedido-cantidad-3',
    ])->json('data');

    $item = $this->postJson("/api/pos/pedidos/{$pedido['id']}/items", [
        'producto_id' => $producto->id,
        'area_preparacion_id' => $area->id,
        'cantidad' => 1,
    ])->json('data.items.0');

    $this->postJson("/api/pos/pedidos/{$pedido['id']}/enviar-comanda")->assertOk();

    $this->patchJson("/api/pos/pedidos/{$pedido['id']}/items/{$item['id']}", ['cantidad' => 5])
        ->assertUnprocessable();

    expect(ItemPedido::find($item['id'])->cantidad)->toBe(1);
});

it('anular un pedido impide seguir agregándole ítems', function () {
    [, $sede, $area, , $producto, $cajero] = crearSedeConCatalogoYUsuarioCaja('Empresa Pedido Anulado');

    $this->actingAs($cajero);

    $pedido = $this->postJson("/api/pos/sedes/{$sede->id}/pedidos", [
        'tipo' => 'mostrador',
        'idempotency_key' => 'pedido-a-anular',
    ])->json('data');

    $this->postJson("/api/pos/pedidos/{$pedido['id']}/anular")->assertOk()->assertJsonPath('data.estado', 'anulado');

    $this->postJson("/api/pos/pedidos/{$pedido['id']}/items", [
        'producto_id' => $producto->id,
        'area_preparacion_id' => $area->id,
        'cantidad' => 1,
    ])->assertUnprocessable();
});

it('anular un pedido con comanda ya enviada a cocina también anula la comanda y notifica al KDS', function () {
    [$empresa, $sede, $area, , $producto, $cajero] = crearSedeConCatalogoYUsuarioCaja('Empresa Anular Con Comanda');
    $cocinero = crearCocineroEnSede($empresa, $sede);

    $this->actingAs($cajero);

    $pedido = $this->postJson("/api/pos/sedes/{$sede->id}/pedidos", [
        'tipo' => 'mostrador',
        'idempotency_key' => 'pedido-anular-con-comanda',
    ])->json('data');

    $this->postJson("/api/pos/pedidos/{$pedido['id']}/items", [
        'producto_id' => $producto->id,
        'area_preparacion_id' => $area->id,
        'cantidad' => 1,
    ]);

    $comandaId = $this->postJson("/api/pos/pedidos/{$pedido['id']}/enviar-comanda")->json('comandas_creadas.0');

    // La comanda avanza a "en preparación" en cocina antes de que el cajero
    // decida anular el pedido — justo el escenario que antes dejaba a
    // cocina preparando una venta que ya no existe.
    $this->actingAs($cocinero);
    $this->postJson("/api/pos/comandas/{$comandaId}/avanzar")->assertOk()->assertJsonPath('data.estado', 'en_preparacion');

    $this->actingAs($cajero);
    Event::fake([ComandaActualizada::class]);

    $this->postJson("/api/pos/pedidos/{$pedido['id']}/anular")->assertOk()->assertJsonPath('data.estado', 'anulado');

    expect(Comanda::find($comandaId)->estado)->toBe(EstadoComanda::Anulada);

    Event::assertDispatched(
        ComandaActualizada::class,
        fn (ComandaActualizada $event) => $event->comanda->id === $comandaId && $event->comanda->estado === EstadoComanda::Anulada
    );
});

it('no se puede anular un pedido con una comanda ya entregada', function () {
    [$empresa, $sede, $area, , $producto, $cajero] = crearSedeConCatalogoYUsuarioCaja('Empresa Anular Con Entregada');
    $cocinero = crearCocineroEnSede($empresa, $sede);

    $this->actingAs($cajero);

    $pedido = $this->postJson("/api/pos/sedes/{$sede->id}/pedidos", [
        'tipo' => 'mostrador',
        'idempotency_key' => 'pedido-anular-con-entregada',
    ])->json('data');

    $this->postJson("/api/pos/pedidos/{$pedido['id']}/items", [
        'producto_id' => $producto->id,
        'area_preparacion_id' => $area->id,
        'cantidad' => 1,
    ]);

    $comandaId = $this->postJson("/api/pos/pedidos/{$pedido['id']}/enviar-comanda")->json('comandas_creadas.0');

    $this->actingAs($cocinero);
    // pendiente -> en_preparacion -> lista -> entregada
    $this->postJson("/api/pos/comandas/{$comandaId}/avanzar");
    $this->postJson("/api/pos/comandas/{$comandaId}/avanzar");
    $this->postJson("/api/pos/comandas/{$comandaId}/avanzar")->assertJsonPath('data.estado', 'entregada');

    $this->actingAs($cajero);
    $this->postJson("/api/pos/pedidos/{$pedido['id']}/anular")->assertUnprocessable();

    expect(Pedido::find($pedido['id'])->estado->value)->toBe('abierto');
    expect(Comanda::find($comandaId)->estado)->toBe(EstadoComanda::Entregada);
});
