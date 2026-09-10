<?php

use App\Enums\MedioPago;
use App\Enums\Rol;
use App\Enums\TipoPedido;
use App\Filament\Widgets\ComparativoSedesWidget;
use App\Filament\Widgets\InventarioBajoWidget;
use App\Filament\Widgets\VentasResumenWidget;
use App\Models\AreaPreparacion;
use App\Models\Insumo;
use App\Models\Inventario;
use App\Models\ItemPedido;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

// crearEmpresaConDosSedesYAdmin()/venderEnSede() vienen de ReporteConsolidadoTest.php
// (mismo proceso Pest, funciones globales — ver docs/DECISIONES.md DEC-065).

it('el resumen consolida ventas, ticket promedio y pedidos de todas las sedes accesibles sin filtro de sede', function () {
    [$empresa, $sedeA, $sedeB, $admin] = crearEmpresaConDosSedesYAdmin('Empresa Dashboard Admin Consolidado');

    venderEnSede($empresa, $sedeA, $admin, '10.00', 'idem-dashadmin-a1');
    venderEnSede($empresa, $sedeB, $admin, '30.00', 'idem-dashadmin-b1');

    Filament::setTenant($empresa, isQuiet: true);
    $this->actingAs($admin);

    Livewire::test(VentasResumenWidget::class, ['pageFilters' => ['sede_id' => null, 'rango' => 'hoy']])
        ->assertSee('$40.00') // consolidado: 10 + 30
        ->assertSee('$20.00'); // ticket promedio: 40 / 2 pedidos
});

it('el resumen se acota a una sola sede cuando el filtro "Sede" está fijado (drill-down)', function () {
    [$empresa, $sedeA, $sedeB, $admin] = crearEmpresaConDosSedesYAdmin('Empresa Dashboard Admin Drilldown');

    venderEnSede($empresa, $sedeA, $admin, '10.00', 'idem-dashdrill-a1');
    venderEnSede($empresa, $sedeB, $admin, '30.00', 'idem-dashdrill-b1');

    Filament::setTenant($empresa, isQuiet: true);
    $this->actingAs($admin);

    Livewire::test(VentasResumenWidget::class, ['pageFilters' => ['sede_id' => $sedeA->id, 'rango' => 'hoy']])
        ->assertSee('$10.00')
        ->assertDontSee('$40.00');
});

it('el comparativo por sede identifica el producto top por cantidad y el empleado destacado por monto cobrado', function () {
    [$empresa, $sedeA, , $admin] = crearEmpresaConDosSedesYAdmin('Empresa Dashboard Admin Comparativo');

    $area = AreaPreparacion::factory()->for($empresa)->for($sedeA)->create();
    $croissant = Producto::factory()->create(['empresa_id' => $empresa->id, 'nombre' => 'Croissant', 'precio' => '5.00']);
    $torta = Producto::factory()->create(['empresa_id' => $empresa->id, 'nombre' => 'Torta', 'precio' => '50.00']);

    $vendedorTop = User::factory()->create(['name' => 'Ana Vendedora']);
    $vendedorTop->accesos()->create(['empresa_id' => $empresa->id, 'sede_id' => $sedeA->id, 'rol' => Rol::Caja]);

    // Ana cobra 5 croissants ($25 en total): cantidad alta, monto bajo — debe
    // ganar "producto top" (por cantidad) pero NO "empleado destacado".
    $pedido1 = Pedido::abrir($sedeA, $vendedorTop, TipoPedido::Mostrador, null, 'idem-comp-1');
    ItemPedido::create([
        'pedido_id' => $pedido1->id, 'producto_id' => $croissant->id, 'area_preparacion_id' => $area->id,
        'nombre_producto' => 'Croissant', 'precio_unitario' => '5.00', 'cantidad' => 5,
    ]);
    Pago::registrar($pedido1, $vendedorTop, MedioPago::Efectivo, $pedido1->total(), 'idem-comp-1-pago');

    // El admin cobra 1 torta ($50): cantidad baja, monto alto — no debe ganar
    // "producto top" (la torta pierde en cantidad), pero SÍ "empleado
    // destacado" (50 > 25 cobrados por Ana).
    $pedido2 = Pedido::abrir($sedeA, $admin, TipoPedido::Mostrador, null, 'idem-comp-2');
    ItemPedido::create([
        'pedido_id' => $pedido2->id, 'producto_id' => $torta->id, 'area_preparacion_id' => $area->id,
        'nombre_producto' => 'Torta', 'precio_unitario' => '50.00', 'cantidad' => 1,
    ]);
    Pago::registrar($pedido2, $admin, MedioPago::Efectivo, $pedido2->total(), 'idem-comp-2-pago');

    Filament::setTenant($empresa, isQuiet: true);
    $this->actingAs($admin);

    $filas = Livewire::test(ComparativoSedesWidget::class, ['pageFilters' => ['sede_id' => $sedeA->id, 'rango' => 'hoy']])
        ->get('filas');

    $fila = $filas->firstWhere('sede.id', $sedeA->id);

    expect($fila['producto_top'])->toBe('Croissant');
    expect($fila['empleado_destacado'])->toBe($admin->name);
});

it('el inventario bajo del dashboard admin marca a qué sede pertenece cada alerta', function () {
    [$empresa, $sedeA, $sedeB, $admin] = crearEmpresaConDosSedesYAdmin('Empresa Dashboard Admin Inventario');

    $insumo = Insumo::factory()->for($empresa)->create(['nombre' => 'Harina', 'stock_minimo' => '500.000']);
    Inventario::factory()->for($empresa)->for($sedeA)->for($insumo)->create(['cantidad_actual' => '100.000']);
    Inventario::factory()->for($empresa)->for($sedeB)->for($insumo)->create(['cantidad_actual' => '900.000']); // sobre el mínimo, sin alerta

    Filament::setTenant($empresa, isQuiet: true);
    $this->actingAs($admin);

    $alertas = Livewire::test(InventarioBajoWidget::class, ['pageFilters' => ['sede_id' => null, 'rango' => 'hoy']])
        ->get('alertas');

    expect($alertas)->toHaveCount(1);
    expect($alertas->first()['sede'])->toBe($sedeA->nombre);
});

it('un administrador de sede con una sola sede accesible no ve datos de otras sedes de la misma empresa', function () {
    [$empresa, $sedeA, $sedeB, $adminCentral] = crearEmpresaConDosSedesYAdmin('Empresa Dashboard Admin Sede Unica');

    $adminSede = User::factory()->create();
    $adminSede->accesos()->create(['empresa_id' => $empresa->id, 'sede_id' => $sedeA->id, 'rol' => Rol::AdministracionSede]);

    venderEnSede($empresa, $sedeA, $adminCentral, '10.00', 'idem-adminsede-a');
    venderEnSede($empresa, $sedeB, $adminCentral, '999.00', 'idem-adminsede-b');

    Filament::setTenant($empresa, isQuiet: true);
    $this->actingAs($adminSede);

    Livewire::test(VentasResumenWidget::class, ['pageFilters' => ['sede_id' => null, 'rango' => 'hoy']])
        ->assertSee('$10.00')
        ->assertDontSee('$999.00')
        ->assertDontSee('$1009.00');
});
