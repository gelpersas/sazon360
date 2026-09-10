<?php

use App\Enums\MedioPago;
use App\Enums\Rol;
use App\Enums\TipoPedido;
use App\Filament\Pages\ReporteConsolidado;
use App\Models\AreaPreparacion;
use App\Models\Empresa;
use App\Models\ItemPedido;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Sede;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

function crearEmpresaConDosSedesYAdmin(string $nombreEmpresa): array
{
    $empresa = Empresa::factory()->create(['nombre' => $nombreEmpresa]);
    $sedeA = Sede::factory()->for($empresa)->create(['nombre' => 'Sede Norte']);
    $sedeB = Sede::factory()->for($empresa)->create(['nombre' => 'Sede Sur']);

    $admin = User::factory()->create();
    $admin->accesos()->create(['empresa_id' => $empresa->id, 'sede_id' => null, 'rol' => Rol::AdministracionCentral]);

    return [$empresa, $sedeA, $sedeB, $admin];
}

function venderEnSede(Empresa $empresa, Sede $sede, User $usuario, string $precio, string $idempotencyKey): Pedido
{
    $area = AreaPreparacion::factory()->for($empresa)->for($sede)->create();
    $producto = Producto::factory()->create(['empresa_id' => $empresa->id, 'precio' => $precio]);
    $pedido = Pedido::abrir($sede, $usuario, TipoPedido::Mostrador, null, $idempotencyKey);

    ItemPedido::create([
        'pedido_id' => $pedido->id,
        'producto_id' => $producto->id,
        'area_preparacion_id' => $area->id,
        'nombre_producto' => $producto->nombre,
        'precio_unitario' => $producto->precio,
        'cantidad' => 1,
    ]);

    Pago::registrar($pedido, $usuario, MedioPago::Efectivo, $pedido->total(), "{$idempotencyKey}-pago");

    return $pedido;
}

it('el reporte consolidado cruza las ventas de dos sedes de la misma empresa', function () {
    [$empresa, $sedeA, $sedeB, $admin] = crearEmpresaConDosSedesYAdmin('Empresa Reporte Consolidado');

    venderEnSede($empresa, $sedeA, $admin, '10.00', 'idem-reporte-a1');
    venderEnSede($empresa, $sedeA, $admin, '15.00', 'idem-reporte-a2');
    venderEnSede($empresa, $sedeB, $admin, '30.00', 'idem-reporte-b1');

    Filament::setTenant($empresa, isQuiet: true);
    $this->actingAs($admin);

    $componente = Livewire::test(ReporteConsolidado::class);

    $filas = $componente->get('filas')->keyBy(fn ($fila) => $fila['sede']->nombre);

    expect($filas['Sede Norte']['total_vendido'])->toBe('25.00');
    expect($filas['Sede Norte']['pedidos_cobrados'])->toBe(2);
    expect($filas['Sede Sur']['total_vendido'])->toBe('30.00');
    expect($filas['Sede Sur']['pedidos_cobrados'])->toBe(1);

    expect($componente->get('totalConsolidado'))->toBe('55.00');
    expect($componente->get('pedidosConsolidados'))->toBe(3);
});

it('solo administración central puede acceder al reporte consolidado', function () {
    [$empresa, $sedeA, , $adminCentral] = crearEmpresaConDosSedesYAdmin('Empresa Permiso Reporte');

    $adminSede = User::factory()->create();
    $adminSede->accesos()->create(['empresa_id' => $empresa->id, 'sede_id' => $sedeA->id, 'rol' => Rol::AdministracionSede]);

    Filament::setTenant($empresa, isQuiet: true);

    expect(ReporteConsolidado::canAccess())->toBeFalse();

    $this->actingAs($adminCentral);
    Filament::setTenant($empresa, isQuiet: true);
    expect(ReporteConsolidado::canAccess())->toBeTrue();

    $this->actingAs($adminSede);
    Filament::setTenant($empresa, isQuiet: true);
    expect(ReporteConsolidado::canAccess())->toBeFalse();
});

it('el reporte consolidado respeta el aislamiento por tenant', function () {
    [$empresaA, $sedeA, , $adminA] = crearEmpresaConDosSedesYAdmin('Empresa Reporte Aislamiento A');
    [$empresaB, , $sedeB, $adminB] = crearEmpresaConDosSedesYAdmin('Empresa Reporte Aislamiento B');

    venderEnSede($empresaA, $sedeA, $adminA, '10.00', 'idem-aislamiento-a');
    venderEnSede($empresaB, $sedeB, $adminB, '999.00', 'idem-aislamiento-b');

    Filament::setTenant($empresaA, isQuiet: true);
    $this->actingAs($adminA);

    $componente = Livewire::test(ReporteConsolidado::class);

    expect($componente->get('totalConsolidado'))->toBe('10.00');
});
