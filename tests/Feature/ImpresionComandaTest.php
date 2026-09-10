<?php

use App\Enums\EstadoComanda;
use App\Enums\TipoPedido;
use App\Events\ComandaActualizada;
use App\Jobs\ImprimirComandaJob;
use App\Listeners\ImprimirComandaAlEnviar;
use App\Models\AreaPreparacion;
use App\Models\Comanda;
use App\Models\ItemPedido;
use App\Models\Pedido;
use App\Services\Impresion\TicketComanda;
use Illuminate\Support\Facades\Queue;
use Mike42\Escpos\PrintConnectors\MemoryPrintConnector;
use Mike42\Escpos\Printer;

// crearSedeConCatalogoYUsuarioCaja() viene de PosApiTest.php (mismo proceso
// Pest, función global) — devuelve [empresa, sede, area, mesa, producto, cajero].

it('un área sin IP o sin puerto configurado no tiene impresora', function () {
    $area = AreaPreparacion::factory()->create(['impresora_ip' => null, 'impresora_puerto' => null]);
    expect($area->tieneImpresora())->toBeFalse();

    $area->impresora_ip = '192.168.0.155';
    expect($area->tieneImpresora())->toBeFalse();

    $area->impresora_puerto = 8022;
    expect($area->tieneImpresora())->toBeTrue();
});

it('el ticket de una comanda incluye el área, el pedido, la mesa y los ítems con cantidad y notas', function () {
    [$empresa, $sede, $area, $mesa, $producto, $cajero] = crearSedeConCatalogoYUsuarioCaja('Empresa Ticket Impresion');

    $pedido = Pedido::abrir($sede, $cajero, TipoPedido::Mesa, $mesa, 'idem-ticket-1');
    ItemPedido::create([
        'pedido_id' => $pedido->id,
        'producto_id' => $producto->id,
        'area_preparacion_id' => $area->id,
        'nombre_producto' => $producto->nombre,
        'precio_unitario' => $producto->precio,
        'cantidad' => 3,
        'notas' => 'Sin azúcar',
    ]);

    $comandas = $pedido->enviarComanda();
    $comanda = $comandas->first();

    $conector = new MemoryPrintConnector;
    $printer = new Printer($conector);
    TicketComanda::imprimir($comanda, $printer);
    $contenido = $conector->getData();
    $printer->close();

    expect($contenido)->toContain($area->nombre);
    expect($contenido)->toContain("Pedido #{$pedido->id}");
    expect($contenido)->toContain("Mesa: {$mesa->nombre}");
    expect($contenido)->toContain("3x {$producto->nombre}");
    // "Nota: Sin az" sin el acento: la librería ESC/POS convierte el texto
    // a la codificación de un byte que entiende la impresora térmica
    // (no UTF-8 crudo) — la "ú" queda como otro byte, no como el carácter
    // UTF-8 literal, así que se verifica el prefijo sin acento.
    expect($contenido)->toContain('Nota: Sin az');
});

it('enviar una comanda a un área con impresora configurada encola el job de impresión', function () {
    Queue::fake();

    [$empresa, $sede, $area, $mesa, $producto, $cajero] = crearSedeConCatalogoYUsuarioCaja('Empresa Impresion Encolada');
    $area->update(['impresora_ip' => '192.168.0.155', 'impresora_puerto' => 8022]);

    $pedido = Pedido::abrir($sede, $cajero, TipoPedido::Mesa, $mesa, 'idem-encola-1');
    ItemPedido::create([
        'pedido_id' => $pedido->id,
        'producto_id' => $producto->id,
        'area_preparacion_id' => $area->id,
        'nombre_producto' => $producto->nombre,
        'precio_unitario' => $producto->precio,
        'cantidad' => 1,
    ]);

    $pedido->enviarComanda();

    Queue::assertPushed(ImprimirComandaJob::class, 1);
});

it('enviar una comanda a un área sin impresora configurada NO encola nada', function () {
    Queue::fake();

    [$empresa, $sede, $area, $mesa, $producto, $cajero] = crearSedeConCatalogoYUsuarioCaja('Empresa Sin Impresion');

    $pedido = Pedido::abrir($sede, $cajero, TipoPedido::Mesa, $mesa, 'idem-sin-impresion-1');
    ItemPedido::create([
        'pedido_id' => $pedido->id,
        'producto_id' => $producto->id,
        'area_preparacion_id' => $area->id,
        'nombre_producto' => $producto->nombre,
        'precio_unitario' => $producto->precio,
        'cantidad' => 1,
    ]);

    $pedido->enviarComanda();

    Queue::assertNotPushed(ImprimirComandaJob::class);
});

it('avanzar el estado de una comanda ya impresa no vuelve a encolar la impresión', function () {
    Queue::fake();

    [$empresa, $sede, $area, $mesa, $producto, $cajero] = crearSedeConCatalogoYUsuarioCaja('Empresa No Reimprimir');
    $area->update(['impresora_ip' => '192.168.0.155', 'impresora_puerto' => 8022]);

    $pedido = Pedido::abrir($sede, $cajero, TipoPedido::Mesa, $mesa, 'idem-no-reimprimir-1');
    ItemPedido::create([
        'pedido_id' => $pedido->id,
        'producto_id' => $producto->id,
        'area_preparacion_id' => $area->id,
        'nombre_producto' => $producto->nombre,
        'precio_unitario' => $producto->precio,
        'cantidad' => 1,
    ]);

    $comanda = $pedido->enviarComanda()->first();
    Queue::assertPushed(ImprimirComandaJob::class, 1);

    $comanda->avanzar();

    Queue::assertPushed(ImprimirComandaJob::class, 1);
});

it('el listener ignora comandas de un área sin impresora aunque el estado sea pendiente', function () {
    Queue::fake();

    $comanda = Comanda::factory()->create(['estado' => EstadoComanda::Pendiente]);

    (new ImprimirComandaAlEnviar)->handle(new ComandaActualizada($comanda));

    Queue::assertNotPushed(ImprimirComandaJob::class);
});
