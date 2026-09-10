<?php

use App\Enums\EstadoComanda;
use App\Enums\Rol;
use App\Enums\TipoPedido;
use App\Events\ComandaActualizada;
use App\Models\AreaPreparacion;
use App\Models\Empresa;
use App\Models\ItemPedido;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Sede;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\Factory;
use Illuminate\Support\Facades\Event;

/**
 * El driver 'null' de broadcasting (default en tests, ver phpunit.xml) no
 * ejecuta ninguna autorización real — para probar routes/channels.php de
 * verdad hace falta un driver que sí la evalúe (mismo mecanismo que
 * 'reverb', sin necesitar un servidor real: auth() es lógica local, no de
 * red). `Broadcast::channel()` registra los canales en el driver activo EN
 * ESE MOMENTO (no en el que se configure después), así que hay que volver a
 * cargar routes/channels.php una vez cambiado el driver por defecto.
 */
function activarDriverDePruebaParaCanales(): void
{
    config([
        'broadcasting.default' => 'pusher',
        'broadcasting.connections.pusher.key' => 'test',
        'broadcasting.connections.pusher.secret' => 'test',
        'broadcasting.connections.pusher.app_id' => 'test',
    ]);

    require base_path('routes/channels.php');
}

function crearPedidoConItemPendiente(string $nombreEmpresa): array
{
    $empresa = Empresa::factory()->create(['nombre' => $nombreEmpresa]);
    $sede = Sede::factory()->for($empresa)->create();
    $area = AreaPreparacion::factory()->for($empresa)->for($sede)->create();
    $producto = Producto::factory()->create(['empresa_id' => $empresa->id, 'precio' => '5.00']);

    $usuario = User::factory()->create();
    $usuario->accesos()->create(['empresa_id' => $empresa->id, 'sede_id' => $sede->id, 'rol' => Rol::Caja]);

    $pedido = Pedido::abrir($sede, $usuario, TipoPedido::Mostrador, null, "idem-kds-{$nombreEmpresa}");

    ItemPedido::create([
        'pedido_id' => $pedido->id,
        'producto_id' => $producto->id,
        'area_preparacion_id' => $area->id,
        'nombre_producto' => $producto->nombre,
        'precio_unitario' => $producto->precio,
        'cantidad' => 2,
    ]);

    return [$empresa, $sede, $usuario, $pedido];
}

it('enviarComanda() dispara ComandaActualizada por cada comanda creada', function () {
    Event::fake([ComandaActualizada::class]);

    [, , , $pedido] = crearPedidoConItemPendiente('Empresa KDS Enviar');

    $comandas = $pedido->enviarComanda();

    expect($comandas)->toHaveCount(1);
    Event::assertDispatched(ComandaActualizada::class, fn (ComandaActualizada $event) => $event->comanda->is($comandas->first()));
});

/**
 * Simula exactamente el bug real encontrado en producción (2026-09-07): con
 * Reverb caído, `ComandaActualizada` (ShouldBroadcastNow, síncrono) lanzaba
 * una `BroadcastException` que tumbaba con un 500 toda la acción de
 * negocio — la comanda SÍ se creaba/avanzaba en base de datos, pero el
 * mesero/cocinero veía "no se puede enviar/avanzar" igual. Se reemplazó
 * `dispatch()` por `dispatchSeguro()` en ambos puntos — ver el docblock de
 * ComandaActualizada::dispatchSeguro().
 */
function romperBroadcastParaEstaPrueba(): void
{
    app()->singleton(Factory::class, fn () => new class implements Factory
    {
        public function connection($name = null)
        {
            throw new RuntimeException('Reverb no disponible (simulado en la prueba)');
        }
    });
}

it('enviarComanda() crea la comanda igual aunque el broadcast al KDS falle (ej. Reverb caído)', function () {
    romperBroadcastParaEstaPrueba();

    [, , , $pedido] = crearPedidoConItemPendiente('Empresa KDS Broadcast Caido Enviar');

    $comandas = $pedido->enviarComanda();

    expect($comandas)->toHaveCount(1);
    expect($comandas->first())->not->toBeNull();
});

it('Comanda::avanzar() avanza el estado igual aunque el broadcast al KDS falle (ej. Reverb caído)', function () {
    [, , , $pedido] = crearPedidoConItemPendiente('Empresa KDS Broadcast Caido Avanzar');
    $comanda = $pedido->enviarComanda()->first();

    romperBroadcastParaEstaPrueba();

    $comanda->avanzar();

    expect($comanda->fresh()->estado)->toBe(EstadoComanda::EnPreparacion);
});

it('Comanda::avanzar() dispara ComandaActualizada', function () {
    [, , , $pedido] = crearPedidoConItemPendiente('Empresa KDS Avanzar');
    $comanda = $pedido->enviarComanda()->first();

    Event::fake([ComandaActualizada::class]);

    $comanda->avanzar();

    Event::assertDispatched(ComandaActualizada::class, fn (ComandaActualizada $event) => $event->comanda->is($comanda) && $event->comanda->estado === EstadoComanda::EnPreparacion);
});

it('ComandaActualizada transmite por el canal privado de la sede con la forma esperada', function () {
    [, $sede, , $pedido] = crearPedidoConItemPendiente('Empresa KDS Payload');
    $comanda = $pedido->enviarComanda()->first();

    $event = new ComandaActualizada($comanda->fresh());

    $canales = $event->broadcastOn();
    expect($canales)->toHaveCount(1);
    expect($canales[0])->toBeInstanceOf(PrivateChannel::class);
    expect($canales[0]->name)->toBe("private-sede.{$sede->id}.comandas");

    expect($event->broadcastAs())->toBe('comanda.actualizada');

    $payload = $event->broadcastWith();
    expect($payload['id'])->toBe($comanda->id);
    expect($payload['estado'])->toBe('pendiente');
    expect($payload['pedido_id'])->toBe($pedido->id);
    expect($payload['items'])->toHaveCount(1);
    expect($payload['items'][0]['cantidad'])->toBe(2);
});

it('el canal privado de comandas de una sede autoriza a un usuario con acceso a esa sede', function () {
    [, $sede, $cajero] = crearPedidoConItemPendiente('Empresa KDS Canal Autorizado');

    activarDriverDePruebaParaCanales();

    $this->actingAs($cajero)
        ->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-sede.{$sede->id}.comandas",
        ])
        ->assertOk();
});

it('el canal privado de comandas de una sede rechaza a un usuario sin acceso a esa sede', function () {
    [, $sede] = crearPedidoConItemPendiente('Empresa KDS Canal Rechazado');
    $sinAcceso = User::factory()->create();

    activarDriverDePruebaParaCanales();

    $this->actingAs($sinAcceso)
        ->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-sede.{$sede->id}.comandas",
        ])
        ->assertForbidden();
});
