<?php

use App\Enums\EstadoPedido;
use App\Enums\MedioPago;
use App\Enums\Rol;
use App\Enums\TipoPedido;
use App\Filament\Resources\Ventas\Pages\ListVentas;
use App\Models\Empresa;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\Sede;
use App\Models\User;
use App\Support\ReciboPdf;
use Filament\Facades\Filament;
use Livewire\Livewire;

// crearEmpresaConDosSedesYAdmin()/venderEnSede() vienen de ReporteConsolidadoTest.php
// (mismo proceso Pest, funciones globales — ver docs/DECISIONES.md DEC-067).

it('administración central ve las ventas de todas las sedes de su empresa', function () {
    [$empresa, $sedeA, $sedeB, $admin] = crearEmpresaConDosSedesYAdmin('Empresa Ventas Central');

    $ventaA = venderEnSede($empresa, $sedeA, $admin, '10.00', 'idem-venta-central-a');
    $ventaB = venderEnSede($empresa, $sedeB, $admin, '20.00', 'idem-venta-central-b');

    Filament::setTenant($empresa, isQuiet: true);
    $this->actingAs($admin);

    Livewire::test(ListVentas::class)
        ->assertCanSeeTableRecords([$ventaA, $ventaB]);
});

it('administración de sede solo ve las ventas de su propia sede', function () {
    [$empresa, $sedeA, $sedeB, $adminCentral] = crearEmpresaConDosSedesYAdmin('Empresa Ventas Sede');

    $adminSede = User::factory()->create();
    $adminSede->accesos()->create(['empresa_id' => $empresa->id, 'sede_id' => $sedeA->id, 'rol' => Rol::AdministracionSede]);

    $ventaA = venderEnSede($empresa, $sedeA, $adminCentral, '10.00', 'idem-venta-sede-a');
    $ventaB = venderEnSede($empresa, $sedeB, $adminCentral, '20.00', 'idem-venta-sede-b');

    Filament::setTenant($empresa, isQuiet: true);
    $this->actingAs($adminSede);

    Livewire::test(ListVentas::class)
        ->assertCanSeeTableRecords([$ventaA])
        ->assertCanNotSeeTableRecords([$ventaB]);
});

it('respeta el aislamiento por tenant: no ve ventas de otra empresa', function () {
    // crearEmpresaConAdminCentral() (de AislamientoMultiempresaTest.php) en
    // vez de crearEmpresaConDosSedesYAdmin(): esta última fija los nombres
    // de sede como "Sede Norte"/"Sede Sur" en TODAS las empresas que crea,
    // así que dos llamadas (una por empresa) producen sedes con el MISMO
    // nombre — assertDontSee($sedeB->nombre) daría un falso positivo al
    // chocar con la sede homónima de la propia empresa A.
    [$empresaA, $sedeA, $adminA] = crearEmpresaConAdminCentral('Empresa Ventas Aislamiento A');
    [$empresaB, $sedeB, $adminB] = crearEmpresaConAdminCentral('Empresa Ventas Aislamiento B');

    venderEnSede($empresaA, $sedeA, $adminA, '10.00', 'idem-venta-aisl-a');
    venderEnSede($empresaB, $sedeB, $adminB, '999.00', 'idem-venta-aisl-b');

    // Request HTTP real (no Livewire::test()+setTenant manual): el global
    // scope de tenant de Filament (BelongsToTenant::registerTenancyModelGlobalScope())
    // solo se activa dentro del ciclo real de una request/middleware — con
    // setTenant() manual el aislamiento automático no se ejercita, aunque
    // en producción sí funciona. Mismo patrón que
    // tests/Feature/AislamientoMultiempresaTest.php.
    $this->actingAs($adminA)
        ->get("/admin/{$empresaA->slug}/ventas")
        ->assertOk()
        ->assertDontSee($sedeB->nombre)
        ->assertDontSee('999.00');
});

it('el filtro de estado muestra solo las ventas cobradas cuando se filtra por ese estado', function () {
    [$empresa, $sede, , $admin] = crearEmpresaConDosSedesYAdmin('Empresa Ventas Filtro Estado');

    $ventaCobrada = venderEnSede($empresa, $sede, $admin, '10.00', 'idem-venta-filtro-1');
    $pedidoAbierto = Pedido::abrir($sede, $admin, TipoPedido::Mostrador, null, 'idem-venta-filtro-2');

    Filament::setTenant($empresa, isQuiet: true);
    $this->actingAs($admin);

    Livewire::test(ListVentas::class)
        ->filterTable('estado', EstadoPedido::Cobrado)
        ->assertCanSeeTableRecords([$ventaCobrada])
        ->assertCanNotSeeTableRecords([$pedidoAbierto]);
});

it('el filtro de sede acota el listado a la sede elegida', function () {
    [$empresa, $sedeA, $sedeB, $admin] = crearEmpresaConDosSedesYAdmin('Empresa Ventas Filtro Sede');

    $ventaA = venderEnSede($empresa, $sedeA, $admin, '10.00', 'idem-venta-fsede-a');
    $ventaB = venderEnSede($empresa, $sedeB, $admin, '20.00', 'idem-venta-fsede-b');

    Filament::setTenant($empresa, isQuiet: true);
    $this->actingAs($admin);

    Livewire::test(ListVentas::class)
        ->filterTable('sede_id', $sedeA)
        ->assertCanSeeTableRecords([$ventaA])
        ->assertCanNotSeeTableRecords([$ventaB]);
});

it('el filtro de medio de pago acota el listado al medio elegido', function () {
    [$empresa, $sede, , $admin] = crearEmpresaConDosSedesYAdmin('Empresa Ventas Filtro Medio');

    $ventaEfectivo = venderEnSede($empresa, $sede, $admin, '10.00', 'idem-venta-medio-1');

    $ventaTarjeta = Pedido::abrir($sede, $admin, TipoPedido::Mostrador, null, 'idem-venta-medio-2');
    Pago::registrar($ventaTarjeta, $admin, MedioPago::Tarjeta, '15.00', 'idem-venta-medio-2-pago');

    Filament::setTenant($empresa, isQuiet: true);
    $this->actingAs($admin);

    Livewire::test(ListVentas::class)
        ->filterTable('medio_pago', MedioPago::Tarjeta)
        ->assertCanSeeTableRecords([$ventaTarjeta])
        ->assertCanNotSeeTableRecords([$ventaEfectivo]);
});

it('genera el recibo PDF de una venta con los datos reales del pedido', function () {
    [$empresa, $sede, , $admin] = crearEmpresaConDosSedesYAdmin('Empresa Recibo PDF');

    $venta = venderEnSede($empresa, $sede, $admin, '12.50', 'idem-recibo-1');

    $respuesta = ReciboPdf::generar($venta->fresh());

    expect($respuesta->headers->get('Content-Type'))->toContain('application/pdf');
    expect($respuesta->headers->get('Content-Disposition'))->toContain("recibo-venta-{$venta->id}.pdf");
});
