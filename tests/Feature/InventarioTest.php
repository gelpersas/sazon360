<?php

use App\Enums\MedioPago;
use App\Enums\Rol;
use App\Enums\TipoMovimientoInventario;
use App\Enums\TipoPedido;
use App\Filament\Resources\Inventarios\Pages\ListInventarios;
use App\Models\AreaPreparacion;
use App\Models\Empresa;
use App\Models\Insumo;
use App\Models\Inventario;
use App\Models\ItemPedido;
use App\Models\MovimientoInventario;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\RecetaItem;
use App\Models\Sede;
use App\Models\User;
use App\Policies\InsumoPolicy;
use Filament\Facades\Filament;
use Livewire\Livewire;

function crearEmpresaConAdminYSede(string $nombreEmpresa): array
{
    $empresa = Empresa::factory()->create(['nombre' => $nombreEmpresa]);
    $sede = Sede::factory()->for($empresa)->create();

    $admin = User::factory()->create();
    $admin->accesos()->create([
        'empresa_id' => $empresa->id,
        'sede_id' => null,
        'rol' => Rol::AdministracionCentral,
    ]);

    return [$empresa, $sede, $admin];
}

it('vender un producto con receta descuenta correctamente sus insumos y queda un movimiento registrado', function () {
    [$empresa, $sede, $admin] = crearEmpresaConAdminYSede('Empresa Receta Venta');

    $harina = Insumo::factory()->for($empresa)->create(['nombre' => 'Harina', 'unidad_medida' => 'g']);
    Inventario::create([
        'empresa_id' => $empresa->id,
        'sede_id' => $sede->id,
        'insumo_id' => $harina->id,
        'cantidad_actual' => 1000,
    ]);

    $producto = Producto::factory()->create(['empresa_id' => $empresa->id, 'precio' => '5.00']);
    RecetaItem::create([
        'producto_id' => $producto->id,
        'insumo_id' => $harina->id,
        'cantidad' => 150, // 150g de harina por unidad vendida
    ]);

    $pedido = Pedido::abrir($sede, $admin, TipoPedido::Mostrador, null, 'idem-venta-receta-1');

    ItemPedido::create([
        'pedido_id' => $pedido->id,
        'producto_id' => $producto->id,
        'area_preparacion_id' => AreaPreparacion::factory()->for($empresa)->for($sede)->create()->id,
        'nombre_producto' => $producto->nombre,
        'precio_unitario' => $producto->precio,
        'cantidad' => 3, // 3 unidades → 450g de harina
    ]);

    Pago::registrar($pedido, $admin, MedioPago::Efectivo, $pedido->total(), 'idem-pago-receta-1');

    $inventarioFinal = Inventario::where('sede_id', $sede->id)->where('insumo_id', $harina->id)->first();

    expect($inventarioFinal->cantidad_actual)->toBe('550.000'); // 1000 - 450

    $movimiento = MovimientoInventario::where('insumo_id', $harina->id)->first();
    expect($movimiento)->not->toBeNull();
    expect($movimiento->tipo)->toBe(TipoMovimientoInventario::Salida);
    expect($movimiento->cantidad)->toBe('450.000');
    expect($movimiento->pedido_id)->toBe($pedido->id);
});

it('un producto sin receta no genera ningún movimiento de inventario al venderse', function () {
    [$empresa, $sede, $admin] = crearEmpresaConAdminYSede('Empresa Sin Receta');

    $producto = Producto::factory()->create(['empresa_id' => $empresa->id, 'precio' => '5.00']);

    $pedido = Pedido::abrir($sede, $admin, TipoPedido::Mostrador, null, 'idem-sin-receta-1');

    ItemPedido::create([
        'pedido_id' => $pedido->id,
        'producto_id' => $producto->id,
        'area_preparacion_id' => AreaPreparacion::factory()->for($empresa)->for($sede)->create()->id,
        'nombre_producto' => $producto->nombre,
        'precio_unitario' => $producto->precio,
        'cantidad' => 1,
    ]);

    Pago::registrar($pedido, $admin, MedioPago::Efectivo, $pedido->total(), 'idem-pago-sin-receta-1');

    expect(MovimientoInventario::count())->toBe(0);
});

it('registrarMovimiento: entrada suma, salida resta y ajuste fija el valor absoluto', function () {
    [$empresa, $sede, $admin] = crearEmpresaConAdminYSede('Empresa Movimientos Manuales');
    $leche = Insumo::factory()->for($empresa)->create(['nombre' => 'Leche', 'unidad_medida' => 'l']);

    Inventario::registrarMovimiento($sede, $leche, $admin, TipoMovimientoInventario::Entrada, '10', 'Compra inicial');
    $inv = Inventario::where('insumo_id', $leche->id)->first();
    expect($inv->cantidad_actual)->toBe('10.000');

    Inventario::registrarMovimiento($sede, $leche, $admin, TipoMovimientoInventario::Salida, '3', 'Se rompió una botella');
    expect($inv->fresh()->cantidad_actual)->toBe('7.000');

    Inventario::registrarMovimiento($sede, $leche, $admin, TipoMovimientoInventario::Ajuste, '5.5', 'Conteo físico');
    expect($inv->fresh()->cantidad_actual)->toBe('5.500');

    expect(MovimientoInventario::where('insumo_id', $leche->id)->count())->toBe(3);
});

it('bajoMinimo() detecta correctamente cuando el stock cae por debajo del mínimo', function () {
    [$empresa, $sede] = crearEmpresaConAdminYSede('Empresa Stock Bajo');
    $azucar = Insumo::factory()->for($empresa)->create(['stock_minimo' => 100]);

    $inventario = Inventario::create([
        'empresa_id' => $empresa->id,
        'sede_id' => $sede->id,
        'insumo_id' => $azucar->id,
        'cantidad_actual' => 50,
    ]);

    expect($inventario->bajoMinimo())->toBeTrue();

    $inventario->update(['cantidad_actual' => 150]);
    expect($inventario->fresh()->bajoMinimo())->toBeFalse();
});

it('el listado de inventario de Filament respeta el aislamiento por sede y por tenant', function () {
    [$empresaA, $sedeA, $adminA] = crearEmpresaConAdminYSede('Empresa Inventario A');
    [$empresaB, $sedeB, $adminB] = crearEmpresaConAdminYSede('Empresa Inventario B');

    $insumoA = Insumo::factory()->for($empresaA)->create(['nombre' => 'Insumo Exclusivo A']);
    Inventario::create([
        'empresa_id' => $empresaA->id,
        'sede_id' => $sedeA->id,
        'insumo_id' => $insumoA->id,
        'cantidad_actual' => 10,
    ]);

    $insumoB = Insumo::factory()->for($empresaB)->create(['nombre' => 'Insumo Exclusivo B']);
    Inventario::create([
        'empresa_id' => $empresaB->id,
        'sede_id' => $sedeB->id,
        'insumo_id' => $insumoB->id,
        'cantidad_actual' => 10,
    ]);

    $this->actingAs($adminA)
        ->get("/admin/{$empresaA->slug}/inventarios")
        ->assertOk()
        ->assertSee('Insumo Exclusivo A')
        ->assertDontSee('Insumo Exclusivo B');

    $this->actingAs($adminA)
        ->get("/admin/{$empresaB->slug}/inventarios")
        ->assertNotFound();
});

it('el formulario "Registrar movimiento" del inventario se puede abrir y usar sin errores', function () {
    [$empresa, $sede, $admin] = crearEmpresaConAdminYSede('Empresa Form Movimiento');
    $insumo = Insumo::factory()->for($empresa)->create();

    $this->actingAs($admin);
    $this->get("/admin/{$empresa->slug}/inventarios");

    Livewire::test(ListInventarios::class)
        ->mountAction('registrarMovimiento')
        ->setActionData([
            'sede_id' => $sede->id,
            'insumo_id' => $insumo->id,
            'tipo' => TipoMovimientoInventario::Entrada->value,
            'cantidad' => '20',
            'motivo' => 'Prueba de formulario',
        ])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect(Inventario::where('insumo_id', $insumo->id)->first()->cantidad_actual)->toBe('20.000');
});

it('solo administración central puede crear o editar insumos', function () {
    [$empresa, $sede, $adminCentral] = crearEmpresaConAdminYSede('Empresa Roles Insumo');

    $adminSede = User::factory()->create();
    $adminSede->accesos()->create([
        'empresa_id' => $empresa->id,
        'sede_id' => $sede->id,
        'rol' => Rol::AdministracionSede,
    ]);

    Filament::setTenant($empresa, isQuiet: true);

    $policy = new InsumoPolicy;

    expect($policy->create($adminCentral))->toBeTrue();
    expect($policy->create($adminSede))->toBeFalse();
});
