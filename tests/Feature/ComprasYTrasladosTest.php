<?php

use App\Enums\Rol;
use App\Enums\TipoMovimientoInventario;
use App\Filament\Resources\Compras\Pages\ListCompras;
use App\Filament\Resources\TrasladoInventarios\Pages\ListTrasladoInventarios;
use App\Models\Compra;
use App\Models\Empresa;
use App\Models\Insumo;
use App\Models\Inventario;
use App\Models\MovimientoInventario;
use App\Models\Proveedor;
use App\Models\Sede;
use App\Models\TrasladoInventario;
use App\Models\User;
use App\Policies\CompraPolicy;
use App\Policies\ProveedorPolicy;
use App\Policies\TrasladoInventarioPolicy;
use Filament\Facades\Filament;
use Livewire\Livewire;

function crearEmpresaConAdminYDosSedes(string $nombreEmpresa): array
{
    $empresa = Empresa::factory()->create(['nombre' => $nombreEmpresa]);
    $sedeA = Sede::factory()->for($empresa)->create(['nombre' => 'Sede A']);
    $sedeB = Sede::factory()->for($empresa)->create(['nombre' => 'Sede B']);

    $admin = User::factory()->create();
    $admin->accesos()->create([
        'empresa_id' => $empresa->id,
        'sede_id' => null,
        'rol' => Rol::AdministracionCentral,
    ]);

    return [$empresa, $sedeA, $sedeB, $admin];
}

it('Compra::registrar() aumenta el inventario de la sede y deja un MovimientoInventario de entrada enlazado', function () {
    [$empresa, $sedeA, , $admin] = crearEmpresaConAdminYDosSedes('Empresa Compra Básica');
    $proveedor = Proveedor::create(['empresa_id' => $empresa->id, 'nombre' => 'Distribuidora XYZ', 'estado' => 'activo']);
    $harina = Insumo::factory()->for($empresa)->create(['nombre' => 'Harina', 'unidad_medida' => 'kg']);

    $compra = Compra::registrar(
        sede: $sedeA,
        proveedor: $proveedor,
        usuario: $admin,
        items: [
            ['insumo_id' => $harina->id, 'cantidad' => '20.000', 'costo_unitario' => '3.50'],
        ],
        numeroFacturaProveedor: 'F-001',
    );

    expect($compra->items)->toHaveCount(1);
    expect($compra->total())->toBe('70.00');

    $inventario = Inventario::where('sede_id', $sedeA->id)->where('insumo_id', $harina->id)->first();
    expect($inventario->cantidad_actual)->toBe('20.000');

    $movimiento = MovimientoInventario::where('insumo_id', $harina->id)->first();
    expect($movimiento->tipo)->toBe(TipoMovimientoInventario::Entrada);
    expect($movimiento->cantidad)->toBe('20.000');
    expect($movimiento->compra_id)->toBe($compra->id);
});

it('Compra::registrar() rechaza una compra sin ítems', function () {
    [$empresa, $sedeA, , $admin] = crearEmpresaConAdminYDosSedes('Empresa Compra Vacía');
    $proveedor = Proveedor::create(['empresa_id' => $empresa->id, 'nombre' => 'Proveedor Sin Items', 'estado' => 'activo']);

    Compra::registrar(sede: $sedeA, proveedor: $proveedor, usuario: $admin, items: []);
})->throws(InvalidArgumentException::class);

it('TrasladoInventario::realizar() mueve stock de una sede a otra y deja dos movimientos enlazados', function () {
    [$empresa, $sedeA, $sedeB, $admin] = crearEmpresaConAdminYDosSedes('Empresa Traslado Básico');
    $cafe = Insumo::factory()->for($empresa)->create(['nombre' => 'Café en grano', 'unidad_medida' => 'kg']);

    Inventario::create(['empresa_id' => $empresa->id, 'sede_id' => $sedeA->id, 'insumo_id' => $cafe->id, 'cantidad_actual' => 50]);

    $traslado = TrasladoInventario::realizar(
        insumo: $cafe,
        origen: $sedeA,
        destino: $sedeB,
        usuario: $admin,
        cantidad: '15.000',
        notas: 'Reposición semanal',
    );

    expect(Inventario::where('sede_id', $sedeA->id)->where('insumo_id', $cafe->id)->first()->cantidad_actual)->toBe('35.000');
    expect(Inventario::where('sede_id', $sedeB->id)->where('insumo_id', $cafe->id)->first()->cantidad_actual)->toBe('15.000');

    $salida = MovimientoInventario::where('sede_id', $sedeA->id)->where('traslado_inventario_id', $traslado->id)->first();
    expect($salida->tipo)->toBe(TipoMovimientoInventario::Salida);
    expect($salida->cantidad)->toBe('15.000');

    $entrada = MovimientoInventario::where('sede_id', $sedeB->id)->where('traslado_inventario_id', $traslado->id)->first();
    expect($entrada->tipo)->toBe(TipoMovimientoInventario::Entrada);
    expect($entrada->cantidad)->toBe('15.000');
});

it('TrasladoInventario::realizar() rechaza trasladar una sede a sí misma', function () {
    [$empresa, $sedeA, , $admin] = crearEmpresaConAdminYDosSedes('Empresa Traslado Mismo');
    $insumo = Insumo::factory()->for($empresa)->create();

    TrasladoInventario::realizar($insumo, $sedeA, $sedeA, $admin, '5.000');
})->throws(InvalidArgumentException::class);

it('TrasladoInventario::realizar() rechaza sedes de una empresa distinta a la del insumo', function () {
    [$empresaA, $sedeA] = crearEmpresaConAdminYDosSedes('Empresa Traslado Cruzado A');
    [$empresaB, , $sedeBdeOtraEmpresa, $adminB] = crearEmpresaConAdminYDosSedes('Empresa Traslado Cruzado B');

    $insumoDeA = Insumo::factory()->for($empresaA)->create();

    TrasladoInventario::realizar($insumoDeA, $sedeA, $sedeBdeOtraEmpresa, $adminB, '5.000');
})->throws(InvalidArgumentException::class);

it('un movimiento de tipo merma resta stock igual que una salida', function () {
    [$empresa, $sedeA, , $admin] = crearEmpresaConAdminYDosSedes('Empresa Merma');
    $azucar = Insumo::factory()->for($empresa)->create(['nombre' => 'Azúcar', 'unidad_medida' => 'kg']);

    Inventario::registrarMovimiento($sedeA, $azucar, $admin, TipoMovimientoInventario::Entrada, '10', 'Compra inicial');
    Inventario::registrarMovimiento($sedeA, $azucar, $admin, TipoMovimientoInventario::Merma, '2', 'Bulto dañado por humedad');

    $inventario = Inventario::where('sede_id', $sedeA->id)->where('insumo_id', $azucar->id)->first();
    expect($inventario->cantidad_actual)->toBe('8.000');

    $merma = MovimientoInventario::where('tipo', TipoMovimientoInventario::Merma)->first();
    expect($merma->cantidad)->toBe('2.000');
    expect($merma->motivo)->toBe('Bulto dañado por humedad');
});

it('solo administración central puede crear proveedores', function () {
    [$empresa, $sedeA, , $adminCentral] = crearEmpresaConAdminYDosSedes('Empresa Permiso Proveedor');

    $adminSede = User::factory()->create();
    $adminSede->accesos()->create(['empresa_id' => $empresa->id, 'sede_id' => $sedeA->id, 'rol' => Rol::AdministracionSede]);

    Filament::setTenant($empresa, isQuiet: true);
    $policy = new ProveedorPolicy;

    expect($policy->create($adminCentral))->toBeTrue();
    expect($policy->create($adminSede))->toBeFalse();
});

it('cualquiera con acceso a una sede puede registrar una compra, no solo administración central', function () {
    [$empresa, $sedeA, , $adminCentral] = crearEmpresaConAdminYDosSedes('Empresa Permiso Compra');

    $cajero = User::factory()->create();
    $cajero->accesos()->create(['empresa_id' => $empresa->id, 'sede_id' => $sedeA->id, 'rol' => Rol::Caja]);

    $sinAcceso = User::factory()->create();

    Filament::setTenant($empresa, isQuiet: true);
    $policy = new CompraPolicy;

    expect($policy->create($adminCentral))->toBeTrue();
    expect($policy->create($cajero))->toBeTrue();
    expect($policy->create($sinAcceso))->toBeFalse();
});

it('un traslado requiere acceso a ambas sedes, no solo a una', function () {
    [$empresa, $sedeA, $sedeB, $adminCentral] = crearEmpresaConAdminYDosSedes('Empresa Permiso Traslado');

    $soloSedeA = User::factory()->create();
    $soloSedeA->accesos()->create(['empresa_id' => $empresa->id, 'sede_id' => $sedeA->id, 'rol' => Rol::AdministracionSede]);

    $ambasSedes = User::factory()->create();
    $ambasSedes->accesos()->create(['empresa_id' => $empresa->id, 'sede_id' => $sedeA->id, 'rol' => Rol::AdministracionSede]);
    $ambasSedes->accesos()->create(['empresa_id' => $empresa->id, 'sede_id' => $sedeB->id, 'rol' => Rol::AdministracionSede]);

    Filament::setTenant($empresa, isQuiet: true);
    $policy = new TrasladoInventarioPolicy;

    expect($policy->create($adminCentral))->toBeTrue();
    expect($policy->create($ambasSedes))->toBeTrue();
    expect($policy->create($soloSedeA))->toBeFalse();
});

it('el botón "Nuevo traslado" solo es visible para quien tiene acceso a 2 o más sedes', function () {
    [$empresa, $sedeA, $sedeB, $adminCentral] = crearEmpresaConAdminYDosSedes('Empresa Visibilidad Traslado');

    $soloSedeA = User::factory()->create();
    $soloSedeA->accesos()->create(['empresa_id' => $empresa->id, 'sede_id' => $sedeA->id, 'rol' => Rol::AdministracionSede]);

    $ambasSedes = User::factory()->create();
    $ambasSedes->accesos()->create(['empresa_id' => $empresa->id, 'sede_id' => $sedeA->id, 'rol' => Rol::AdministracionSede]);
    $ambasSedes->accesos()->create(['empresa_id' => $empresa->id, 'sede_id' => $sedeB->id, 'rol' => Rol::AdministracionSede]);

    $this->actingAs($soloSedeA);
    $this->get("/admin/{$empresa->slug}/traslados-inventario");
    Livewire::test(ListTrasladoInventarios::class)->assertActionHidden('nuevoTraslado');

    $this->actingAs($ambasSedes);
    $this->get("/admin/{$empresa->slug}/traslados-inventario");
    Livewire::test(ListTrasladoInventarios::class)->assertActionVisible('nuevoTraslado');

    $this->actingAs($adminCentral);
    $this->get("/admin/{$empresa->slug}/traslados-inventario");
    Livewire::test(ListTrasladoInventarios::class)->assertActionVisible('nuevoTraslado');
});

it('el formulario "Registrar compra" crea la compra y aumenta inventario sin errores', function () {
    [$empresa, $sedeA, , $admin] = crearEmpresaConAdminYDosSedes('Empresa Form Compra');
    $proveedor = Proveedor::create(['empresa_id' => $empresa->id, 'nombre' => 'Proveedor Form', 'estado' => 'activo']);
    $insumo = Insumo::factory()->for($empresa)->create();

    $this->actingAs($admin);
    $this->get("/admin/{$empresa->slug}/compras");

    // "Registrar compra" ahora es un modal (ver docs/DECISIONES.md DEC-041),
    // no una página aparte.
    Livewire::test(ListCompras::class)
        ->mountAction('create')
        ->setActionData([
            'sede_id' => $sedeA->id,
            'proveedor_id' => $proveedor->id,
            'items' => [
                ['insumo_id' => $insumo->id, 'cantidad' => '5', 'costo_unitario' => '2.00'],
            ],
        ])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect(Compra::count())->toBe(1);
    expect(Inventario::where('insumo_id', $insumo->id)->first()->cantidad_actual)->toBe('5.000');
});

it('la acción "Nuevo traslado" mueve el inventario entre sedes sin errores', function () {
    [$empresa, $sedeA, $sedeB, $admin] = crearEmpresaConAdminYDosSedes('Empresa Form Traslado');
    $insumo = Insumo::factory()->for($empresa)->create();
    Inventario::create(['empresa_id' => $empresa->id, 'sede_id' => $sedeA->id, 'insumo_id' => $insumo->id, 'cantidad_actual' => 30]);

    $this->actingAs($admin);
    $this->get("/admin/{$empresa->slug}/traslados-inventario");

    Livewire::test(ListTrasladoInventarios::class)
        ->mountAction('nuevoTraslado')
        ->setActionData([
            'insumo_id' => $insumo->id,
            'sede_origen_id' => $sedeA->id,
            'sede_destino_id' => $sedeB->id,
            'cantidad' => '10',
        ])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect(Inventario::where('sede_id', $sedeA->id)->where('insumo_id', $insumo->id)->first()->cantidad_actual)->toBe('20.000');
    expect(Inventario::where('sede_id', $sedeB->id)->where('insumo_id', $insumo->id)->first()->cantidad_actual)->toBe('10.000');
});

it('el listado de proveedores, compras y traslados de Filament respeta el aislamiento por tenant', function () {
    [$empresaA, $sedeA, , $adminA] = crearEmpresaConAdminYDosSedes('Empresa Aislamiento Compras A');
    [$empresaB] = crearEmpresaConAdminYDosSedes('Empresa Aislamiento Compras B');

    Proveedor::create(['empresa_id' => $empresaA->id, 'nombre' => 'Proveedor Exclusivo A', 'estado' => 'activo']);
    Proveedor::create(['empresa_id' => $empresaB->id, 'nombre' => 'Proveedor Exclusivo B', 'estado' => 'activo']);

    $this->actingAs($adminA)
        ->get("/admin/{$empresaA->slug}/proveedores")
        ->assertOk()
        ->assertSee('Proveedor Exclusivo A')
        ->assertDontSee('Proveedor Exclusivo B');

    $this->actingAs($adminA)
        ->get("/admin/{$empresaB->slug}/proveedores")
        ->assertNotFound();

    $this->actingAs($adminA)
        ->get("/admin/{$empresaA->slug}/compras")
        ->assertOk();

    $this->actingAs($adminA)
        ->get("/admin/{$empresaA->slug}/traslados-inventario")
        ->assertOk();
});
