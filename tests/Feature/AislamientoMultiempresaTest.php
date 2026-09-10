<?php

use App\Enums\Rol;
use App\Filament\Pages\Tenancy\RegisterEmpresa;
use App\Models\Empresa;
use App\Models\Sede;
use App\Models\User;
use App\Policies\SedePolicy;
use Filament\Facades\Filament;
use Livewire\Livewire;

function crearEmpresaConAdminCentral(string $nombreEmpresa): array
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

it('un usuario sin acceso alguno es redirigido a registrar su primera empresa', function () {
    $sinAcceso = User::factory()->create();

    expect($sinAcceso->accesos()->exists())->toBeFalse();

    // Sin esto, "Registrar empresa" (/admin/new) sería inalcanzable para
    // cualquier usuario nuevo — es el único caso en que canAccessPanel()
    // deja pasar a alguien sin rol de administración.
    $this->actingAs($sinAcceso)
        ->get('/admin')
        ->assertRedirect('/admin/new');

    $this->actingAs($sinAcceso)
        ->get('/admin/new')
        ->assertOk();
});

it('un usuario nuevo puede registrar su primera empresa y queda como administración central', function () {
    $nuevo = User::factory()->create();

    $this->actingAs($nuevo);

    Livewire::test(RegisterEmpresa::class)
        ->fillForm(['nombre' => 'Mi Nueva Empresa'])
        ->call('register')
        ->assertHasNoFormErrors();

    $empresa = Empresa::where('nombre', 'Mi Nueva Empresa')->firstOrFail();

    expect($nuevo->esAdminCentralDe($empresa))->toBeTrue();
});

it('un usuario con rol operativo (mesero) no puede entrar al panel de Filament', function () {
    [$empresa, $sede] = crearEmpresaConAdminCentral('Empresa Mesero');

    $mesero = User::factory()->create();
    $mesero->accesos()->create([
        'empresa_id' => $empresa->id,
        'sede_id' => $sede->id,
        'rol' => Rol::Mesero,
    ]);

    $this->actingAs($mesero)
        ->get("/admin/{$empresa->slug}")
        ->assertForbidden();
});

it('un administrador central puede entrar al panel de su propia empresa', function () {
    [$empresa] = crearEmpresaConAdminCentral('Empresa Propia');

    $this->actingAs($empresa->accesos()->first()->user)
        ->get("/admin/{$empresa->slug}")
        ->assertOk();
});

it('un administrador de una empresa no puede acceder al tenant de otra empresa', function () {
    [$empresaA, , $adminA] = crearEmpresaConAdminCentral('Empresa A');
    [$empresaB] = crearEmpresaConAdminCentral('Empresa B');

    // Filament responde 404 (no 403) para no confirmar que el tenant existe.
    $this->actingAs($adminA)
        ->get("/admin/{$empresaB->slug}")
        ->assertNotFound();
});

it('el listado de sedes de Filament solo muestra sedes del tenant actual', function () {
    [$empresaA, $sedeA, $adminA] = crearEmpresaConAdminCentral('Empresa Listado A');
    [, $sedeB] = crearEmpresaConAdminCentral('Empresa Listado B');

    $response = $this->actingAs($adminA)
        ->get("/admin/{$empresaA->slug}/sedes")
        ->assertOk();

    $response->assertSee($sedeA->nombre);
    $response->assertDontSee($sedeB->nombre);
});

it('las sedes accesibles de un usuario no incluyen sedes de otra empresa', function () {
    [$empresaA, $sedeA, $adminA] = crearEmpresaConAdminCentral('Empresa Sedes A');
    [$empresaB, $sedeB] = crearEmpresaConAdminCentral('Empresa Sedes B');

    $sedesDeA = $adminA->sedesAccesibles($empresaA);

    expect($sedesDeA->pluck('id'))->toContain($sedeA->id);
    expect($sedesDeA->pluck('id'))->not->toContain($sedeB->id);

    // Sin acceso a la empresa B, la lista de sedes accesibles ahí es vacía.
    expect($adminA->sedesAccesibles($empresaB))->toBeEmpty();
});

it('solo administración central puede crear una sede en el tenant actual', function () {
    [$empresa, $sede, $adminCentral] = crearEmpresaConAdminCentral('Empresa Roles');

    $adminSede = User::factory()->create();
    $adminSede->accesos()->create([
        'empresa_id' => $empresa->id,
        'sede_id' => $sede->id,
        'rol' => Rol::AdministracionSede,
    ]);

    Filament::setTenant($empresa, isQuiet: true);

    $policy = new SedePolicy;

    expect($policy->create($adminCentral))->toBeTrue();
    expect($policy->create($adminSede))->toBeFalse();
});

it('un administrador de sede puede actualizar su propia sede pero no eliminarla', function () {
    [$empresa, $sede] = crearEmpresaConAdminCentral('Empresa Permisos Sede');

    $adminSede = User::factory()->create();
    $adminSede->accesos()->create([
        'empresa_id' => $empresa->id,
        'sede_id' => $sede->id,
        'rol' => Rol::AdministracionSede,
    ]);

    $policy = new SedePolicy;

    expect($policy->update($adminSede, $sede))->toBeTrue();
    expect($policy->delete($adminSede, $sede))->toBeFalse();
});

it('un administrador central conserva su rol en una sede aunque tenga además un acceso operativo ahí', function () {
    [$empresa, $sede, $adminCentral] = crearEmpresaConAdminCentral('Empresa Doble Acceso');

    // Caso límite: el mismo usuario, además de admin central, tiene un
    // acceso de caja en una sede concreta de su propia empresa.
    $adminCentral->accesos()->create([
        'empresa_id' => $empresa->id,
        'sede_id' => $sede->id,
        'rol' => Rol::Caja,
    ]);

    expect($adminCentral->rolEnSede($sede))->toBe(Rol::AdministracionCentral);

    $policy = new SedePolicy;
    expect($policy->delete($adminCentral, $sede))->toBeTrue();
});

it('el listado de sedes de Filament solo muestra la sede propia a un administrador de sede', function () {
    [$empresa, $sedeA] = crearEmpresaConAdminCentral('Empresa Sedes Filtradas');
    $sedeB = Sede::factory()->for($empresa)->create();

    $adminSedeA = User::factory()->create();
    $adminSedeA->accesos()->create([
        'empresa_id' => $empresa->id,
        'sede_id' => $sedeA->id,
        'rol' => Rol::AdministracionSede,
    ]);

    $this->actingAs($adminSedeA)
        ->get("/admin/{$empresa->slug}/sedes")
        ->assertOk()
        ->assertSee($sedeA->nombre)
        ->assertDontSee($sedeB->nombre);
});
