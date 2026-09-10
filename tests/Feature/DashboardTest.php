<?php

use App\Enums\EstadoComanda;
use App\Enums\MedioPago;
use App\Enums\ModoGrupoMesa;
use App\Enums\TipoPedido;
use App\Models\Comanda;
use App\Models\GrupoMesa;
use App\Models\Insumo;
use App\Models\Inventario;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\User;

it('un usuario sin ningún acceso a la sede no puede ver su dashboard', function () {
    [, $sede, , , , $cajero] = crearSedeConCatalogoYUsuarioCaja('Empresa Dashboard Sin Acceso');
    $intruso = User::factory()->create();

    $this->actingAs($intruso, 'sanctum')
        ->getJson("/api/pos/sedes/{$sede->id}/dashboard")
        ->assertForbidden();
});

it('rechaza un rango que no sea hoy, semana o mes', function () {
    [, $sede, , , , $cajero] = crearSedeConCatalogoYUsuarioCaja('Empresa Dashboard Rango Invalido');

    $this->actingAs($cajero, 'sanctum')
        ->getJson("/api/pos/sedes/{$sede->id}/dashboard?rango=año")
        ->assertStatus(422);
});

it('calcula ventas del rango, pedidos activos e inventario bajo de la sede', function () {
    [$empresa, $sede, $area, $mesa, $producto, $cajero] = crearSedeConCatalogoYUsuarioCaja('Empresa Dashboard Metricas');

    $pedidoCobrado = Pedido::abrir($sede, $cajero, TipoPedido::Mostrador, null, 'idem-dash-1');
    Pago::registrar($pedidoCobrado, $cajero, MedioPago::Efectivo, '42.50', 'idem-dash-1-pago');

    $pedidoEnCocina = Pedido::abrir($sede, $cajero, TipoPedido::Mostrador, null, 'idem-dash-2');
    Comanda::factory()->for($empresa)->for($pedidoEnCocina)->for($area)->create(['estado' => EstadoComanda::EnPreparacion]);

    $insumo = Insumo::factory()->for($empresa)->create(['stock_minimo' => '500.000']);
    Inventario::factory()->for($empresa)->for($sede)->for($insumo)->create(['cantidad_actual' => '100.000']);

    $respuesta = $this->actingAs($cajero, 'sanctum')
        ->getJson("/api/pos/sedes/{$sede->id}/dashboard?rango=hoy")
        ->assertOk()
        ->json('data');

    expect($respuesta['ventas_total'])->toBe('42.50');
    expect($respuesta['pedidos_activos'])->toBe(1);
    expect($respuesta['inventario_bajo'])->toHaveCount(1);
    expect($respuesta['inventario_bajo'][0]['insumo'])->toBe($insumo->nombre);
});

it('cuenta como ocupada toda mesa unida en Modo General aunque el pedido viva solo en la mesa principal', function () {
    [$empresa, $sede, $mesas, $cajero] = crearSedeConMesasYCajero('Empresa Dashboard Modo General', 3);

    $grupo = GrupoMesa::unir($sede, $mesas, $cajero, ModoGrupoMesa::General);
    Pedido::abrir($sede, $cajero, TipoPedido::Mesa, $grupo->mesaPrincipal, 'idem-dash-grupo');

    $respuesta = $this->actingAs($cajero, 'sanctum')
        ->getJson("/api/pos/sedes/{$sede->id}/dashboard?rango=hoy")
        ->assertOk()
        ->json('data');

    expect($respuesta['mesas_ocupadas'])->toBe(3);
});

it('no mezcla ventas de otra sede de la misma empresa', function () {
    [$empresa, $sedeA, $areaA, , $productoA, $cajeroA] = crearSedeConCatalogoYUsuarioCaja('Empresa Dashboard Aislamiento Sede');
    [, $sedeB, , , , $cajeroB] = crearSedeConCatalogoYUsuarioCaja('Empresa Dashboard Aislamiento Sede Otra');

    $pedidoB = Pedido::abrir($sedeB, $cajeroB, TipoPedido::Mostrador, null, 'idem-dash-otra-sede');
    Pago::registrar($pedidoB, $cajeroB, MedioPago::Efectivo, '999.00', 'idem-dash-otra-sede-pago');

    $respuesta = $this->actingAs($cajeroA, 'sanctum')
        ->getJson("/api/pos/sedes/{$sedeA->id}/dashboard?rango=hoy")
        ->assertOk()
        ->json('data');

    expect($respuesta['ventas_total'])->toBe('0.00');
});
