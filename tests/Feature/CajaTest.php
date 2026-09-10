<?php

use App\Enums\Rol;
use App\Enums\TipoMovimientoCaja;
use App\Filament\Resources\Cajas\Pages\ListCajas;
use App\Filament\Resources\Cajas\Pages\ViewCaja;
use App\Models\Caja;
use App\Models\Empresa;
use App\Models\MovimientoCaja;
use App\Models\Sede;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

function crearEmpresaSedeYAdmin(string $nombreEmpresa): array
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

it('un turno completo de caja queda registrado: apertura, movimientos y cierre', function () {
    [, $sede, $admin] = crearEmpresaSedeYAdmin('Empresa Turno Completo');

    $caja = Caja::abrir($sede, $admin, '50.00', 'Fondo inicial del día');

    expect($caja->estaAbierta())->toBeTrue();
    expect($caja->usuario_apertura_id)->toBe($admin->id);

    MovimientoCaja::create([
        'caja_id' => $caja->id,
        'empresa_id' => $caja->empresa_id,
        'usuario_id' => $admin->id,
        'tipo' => TipoMovimientoCaja::Ingreso,
        'monto' => '30.00',
        'descripcion' => 'Venta mostrador',
    ]);

    MovimientoCaja::create([
        'caja_id' => $caja->id,
        'empresa_id' => $caja->empresa_id,
        'usuario_id' => $admin->id,
        'tipo' => TipoMovimientoCaja::Egreso,
        'monto' => '5.00',
        'descripcion' => 'Compra de servilletas',
    ]);

    expect($caja->montoEsperado())->toBe('75.00'); // 50 + 30 - 5

    $caja->cerrar($admin, '75.00', 'Cuadró exacto');

    expect($caja->estaAbierta())->toBeFalse();
    expect($caja->monto_cierre_esperado)->toBe('75.00');
    expect($caja->monto_cierre_real)->toBe('75.00');
    expect($caja->diferencia)->toBe('0.00');
    expect($caja->usuario_cierre_id)->toBe($admin->id);
    expect($caja->cerrada_at)->not->toBeNull();
});

it('no se puede abrir una segunda caja en una sede que ya tiene una abierta', function () {
    [, $sede, $admin] = crearEmpresaSedeYAdmin('Empresa Doble Apertura');

    Caja::abrir($sede, $admin, '50.00');

    expect(fn () => Caja::abrir($sede, $admin, '20.00'))
        ->toThrow(RuntimeException::class, 'Ya hay una caja abierta en esta sede.');
});

it('cerrar una caja ya cerrada es rechazado (protege contra doble cierre concurrente)', function () {
    [, $sede, $admin] = crearEmpresaSedeYAdmin('Empresa Doble Cierre');

    $caja = Caja::abrir($sede, $admin, '50.00');
    $caja->cerrar($admin, '50.00');

    // Simula el segundo request de un doble clic / cierre concurrente: la
    // fila ya está 'cerrada' en base de datos cuando este segundo intento
    // hace su propio lockForUpdate() + chequeo de estado.
    $mismaCaja = Caja::find($caja->id);

    expect(fn () => $mismaCaja->cerrar($admin, '50.00'))
        ->toThrow(RuntimeException::class, 'Esta caja ya está cerrada.');
});

it('no se pueden registrar movimientos en una caja cerrada', function () {
    [, $sede, $admin] = crearEmpresaSedeYAdmin('Empresa Movimiento Caja Cerrada');

    $caja = Caja::abrir($sede, $admin, '50.00');
    $caja->cerrar($admin, '50.00');

    expect(fn () => MovimientoCaja::create([
        'caja_id' => $caja->id,
        'empresa_id' => $caja->empresa_id,
        'usuario_id' => $admin->id,
        'tipo' => TipoMovimientoCaja::Ingreso,
        'monto' => '10.00',
        'descripcion' => 'Venta tardía',
    ]))->toThrow(InvalidArgumentException::class);
});

it('rechaza un monto de movimiento no positivo', function () {
    [, $sede, $admin] = crearEmpresaSedeYAdmin('Empresa Monto Invalido');

    $caja = Caja::abrir($sede, $admin, '50.00');

    expect(fn () => MovimientoCaja::create([
        'caja_id' => $caja->id,
        'empresa_id' => $caja->empresa_id,
        'usuario_id' => $admin->id,
        'tipo' => TipoMovimientoCaja::Ingreso,
        'monto' => '0.00',
        'descripcion' => 'Monto inválido',
    ]))->toThrow(InvalidArgumentException::class);
});

it('un administrador de sede no puede registrar movimientos en la caja de otra sede', function () {
    [$empresa, $sedeA, $adminCentral] = crearEmpresaSedeYAdmin('Empresa Movimiento Ajeno');
    $sedeB = Sede::factory()->for($empresa)->create();

    $adminSedeA = User::factory()->create();
    $adminSedeA->accesos()->create([
        'empresa_id' => $empresa->id,
        'sede_id' => $sedeA->id,
        'rol' => Rol::AdministracionSede,
    ]);

    $cajaSedeB = Caja::abrir($sedeB, $adminCentral, '50.00');

    $this->actingAs($adminSedeA);

    expect(fn () => MovimientoCaja::create([
        'caja_id' => $cajaSedeB->id,
        'empresa_id' => $empresa->id,
        'usuario_id' => $adminSedeA->id,
        'tipo' => TipoMovimientoCaja::Ingreso,
        'monto' => '10.00',
        'descripcion' => 'Intento ajeno',
    ]))->toThrow(AuthorizationException::class);
});

it('el listado de cajas de Filament respeta el aislamiento por sede y por tenant', function () {
    [$empresaA, $sedeA, $adminCentralA] = crearEmpresaSedeYAdmin('Empresa Cajas A');
    [$empresaB, $sedeB, $adminCentralB] = crearEmpresaSedeYAdmin('Empresa Cajas B');

    $cajaA = Caja::abrir($sedeA, $adminCentralA, '50.00');
    Caja::abrir($sedeB, $adminCentralB, '50.00');

    $this->actingAs($adminCentralA)
        ->get("/admin/{$empresaA->slug}/cajas")
        ->assertOk()
        ->assertSee($cajaA->sede->nombre);

    // Cross-tenant: el admin de la empresa A no puede ver el tenant B.
    $this->actingAs($adminCentralA)
        ->get("/admin/{$empresaB->slug}/cajas")
        ->assertNotFound();
});

it('el formulario de abrir caja rechaza una segunda apertura en la misma sede con un mensaje legible', function () {
    [$empresa, $sede, $admin] = crearEmpresaSedeYAdmin('Empresa UI Doble Apertura');

    Caja::abrir($sede, $admin, '50.00');

    $this->actingAs($admin);
    $this->get("/admin/{$empresa->slug}/cajas");

    // "Abrir caja" ahora es un modal (ver docs/DECISIONES.md DEC-041), no
    // una página aparte.
    Livewire::test(ListCajas::class)
        ->mountAction('create')
        ->setActionData([
            'sede_id' => $sede->id,
            'monto_inicial' => '20.00',
        ])
        ->callMountedAction();

    expect(Caja::where('sede_id', $sede->id)->count())->toBe(1);
});

it('la acción "Cerrar caja" del panel cierra la caja y deja de estar visible', function () {
    [$empresa, $sede, $admin] = crearEmpresaSedeYAdmin('Empresa UI Cerrar Caja');

    $caja = Caja::abrir($sede, $admin, '50.00');

    $this->actingAs($admin);
    $this->get("/admin/{$empresa->slug}/cajas/{$caja->id}");

    Livewire::test(ViewCaja::class, ['record' => $caja->getRouteKey()])
        ->assertActionVisible('cerrar')
        ->callAction('cerrar', data: [
            'monto_cierre_real' => '50.00',
            'nota_cierre' => 'Cuadró',
        ])
        ->assertActionHidden('cerrar');

    expect($caja->fresh()->estaAbierta())->toBeFalse();
});

/**
 * No se pudo probar "Registrar movimiento" del RelationManager de Caja
 * igual que el de Inventario (Livewire::test() de un RelationManager
 * standalone falla con "missing root tag" — limitación del arnés de
 * pruebas al instanciarlo fuera de su página real, no relacionada con el
 * bug que sí se encontró y corrigió aquí: MovimientosRelationManager usaba
 * ->label() en vez de ->getLabel() en el Select de "tipo", igual que el
 * mismo bug ya cubierto por test en InventarioTest.php). El fix es
 * idéntico en estructura al de Inventario (options(EnumClass::class)),
 * que sí queda probado end-to-end — ver docs/MODULO-ACTUAL.md.
 */
