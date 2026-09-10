<?php

use App\Enums\Rol;
use App\Filament\Resources\Accesos\Pages\ListAccesos;
use App\Models\Acceso;
use App\Models\Empresa;
use App\Models\Sede;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Livewire\Livewire;

function crearEmpresaConAdminCentralYSedes(string $nombreEmpresa, int $numSedes = 1): array
{
    $empresa = Empresa::factory()->create(['nombre' => $nombreEmpresa]);
    $sedes = Sede::factory()->for($empresa)->count($numSedes)->create();

    $admin = User::factory()->create();
    $admin->accesos()->create([
        'empresa_id' => $empresa->id,
        'sede_id' => null,
        'rol' => Rol::AdministracionCentral,
    ]);

    return [$empresa, $sedes, $admin];
}

it('administración central puede dar de alta un usuario existente en una sede desde el panel', function () {
    [$empresa, $sedes, $admin] = crearEmpresaConAdminCentralYSedes('Empresa Acceso Alta');
    $sede = $sedes->first();

    $mesero = User::factory()->create();

    $this->actingAs($admin);
    $this->get("/admin/{$empresa->slug}/accesos");

    // "Crear usuario" ahora es un modal (ver docs/DECISIONES.md DEC-041), no
    // una página aparte — se prueba con mountAction()/setActionData() en vez
    // de Livewire::test(CreateAcceso::class)->fillForm().
    Livewire::test(ListAccesos::class)
        ->mountAction('create')
        ->setActionData([
            'user_id' => $mesero->id,
            'roles' => ['mesero'],
            'sede_id' => $sede->id,
        ])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect(Acceso::where('user_id', $mesero->id)->where('sede_id', $sede->id)->where('rol', Rol::Mesero)->exists())
        ->toBeTrue();
});

it('en un solo modal se puede dar de alta un usuario con varios roles a la vez', function () {
    [$empresa, $sedes, $admin] = crearEmpresaConAdminCentralYSedes('Empresa Acceso Multi Rol');
    $sede = $sedes->first();

    $usuario = User::factory()->create();

    $this->actingAs($admin);
    $this->get("/admin/{$empresa->slug}/accesos");

    Livewire::test(ListAccesos::class)
        ->mountAction('create')
        ->setActionData([
            'user_id' => $usuario->id,
            'roles' => ['mesero', 'caja'],
            'sede_id' => $sede->id,
        ])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect(Acceso::where('user_id', $usuario->id)->where('sede_id', $sede->id)->pluck('rol')->map(fn (Rol $rol) => $rol->value)->all())
        ->toEqualCanonicalizing(['mesero', 'caja']);
});

it('editar un acceso permite agregarle otro rol sin tocar el que ya tenía', function () {
    [$empresa, $sedes, $admin] = crearEmpresaConAdminCentralYSedes('Empresa Editar Agregar Rol');
    $sede = $sedes->first();

    $usuario = User::factory()->create();
    $acceso = $usuario->accesos()->create(['empresa_id' => $empresa->id, 'sede_id' => $sede->id, 'rol' => Rol::Mesero]);

    $this->actingAs($admin);
    $this->get("/admin/{$empresa->slug}/accesos");

    Livewire::test(ListAccesos::class)
        ->mountTableAction('edit', $acceso)
        ->assertTableActionDataSet(['roles' => ['mesero']])
        ->setTableActionData(['roles' => ['mesero', 'caja'], 'sede_id' => $sede->id])
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    expect(Acceso::where('user_id', $usuario->id)->where('sede_id', $sede->id)->pluck('rol')->map(fn (Rol $rol) => $rol->value)->all())
        ->toEqualCanonicalizing(['mesero', 'caja']);
});

it('editar un acceso permite quitarle un rol que ya no debería tener', function () {
    [$empresa, $sedes, $admin] = crearEmpresaConAdminCentralYSedes('Empresa Editar Quitar Rol');
    $sede = $sedes->first();

    $usuario = User::factory()->create();
    $usuario->accesos()->create(['empresa_id' => $empresa->id, 'sede_id' => $sede->id, 'rol' => Rol::Mesero]);
    $accesoCaja = $usuario->accesos()->create(['empresa_id' => $empresa->id, 'sede_id' => $sede->id, 'rol' => Rol::Caja]);

    $this->actingAs($admin);
    $this->get("/admin/{$empresa->slug}/accesos");

    Livewire::test(ListAccesos::class)
        ->mountTableAction('edit', $accesoCaja)
        ->setTableActionData(['roles' => ['mesero'], 'sede_id' => $sede->id])
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    expect(Acceso::where('user_id', $usuario->id)->where('sede_id', $sede->id)->pluck('rol')->map(fn (Rol $rol) => $rol->value)->all())
        ->toBe(['mesero']);
});

it('rechaza duplicar el mismo usuario, sede y rol', function () {
    [$empresa, $sedes, $admin] = crearEmpresaConAdminCentralYSedes('Empresa Acceso Duplicado');
    $sede = $sedes->first();

    $mesero = User::factory()->create();
    $mesero->accesos()->create(['empresa_id' => $empresa->id, 'sede_id' => $sede->id, 'rol' => Rol::Mesero]);

    $this->actingAs($admin);
    $this->get("/admin/{$empresa->slug}/accesos");

    Livewire::test(ListAccesos::class)
        ->mountAction('create')
        ->setActionData(['user_id' => $mesero->id, 'roles' => ['mesero'], 'sede_id' => $sede->id])
        ->callMountedAction()
        ->assertHasActionErrors(['roles']);
});

it('un administrador de sede no puede otorgar el rol de administración central', function () {
    [$empresa, $sedes] = crearEmpresaConAdminCentralYSedes('Empresa Sin Escalada', 1);
    $sede = $sedes->first();

    $adminSede = User::factory()->create();
    $adminSede->accesos()->create(['empresa_id' => $empresa->id, 'sede_id' => $sede->id, 'rol' => Rol::AdministracionSede]);

    $otro = User::factory()->create();

    $this->actingAs($adminSede);

    expect(fn () => Acceso::create([
        'user_id' => $otro->id,
        'empresa_id' => $empresa->id,
        'sede_id' => $sede->id,
        'rol' => Rol::AdministracionCentral,
    ]))->toThrow(AuthorizationException::class);
});

it('el autoregistro de una empresa nueva sí puede crear su primer acceso de administración central', function () {
    $empresa = Empresa::factory()->create();
    $fundador = User::factory()->create();

    $this->actingAs($fundador);

    $acceso = Acceso::create([
        'user_id' => $fundador->id,
        'empresa_id' => $empresa->id,
        'sede_id' => null,
        'rol' => Rol::AdministracionCentral,
    ]);

    expect($acceso->exists)->toBeTrue();
    expect($fundador->esAdminCentralDe($empresa))->toBeTrue();
});

it('un administrador de sede solo ve los accesos de sus propias sedes, nunca los de administración central', function () {
    [$empresa, $sedes, $adminCentral] = crearEmpresaConAdminCentralYSedes('Empresa Aislamiento Accesos', 2);
    [$sedeA, $sedeB] = $sedes;

    $adminSedeA = User::factory()->create();
    $adminSedeA->accesos()->create(['empresa_id' => $empresa->id, 'sede_id' => $sedeA->id, 'rol' => Rol::AdministracionSede]);

    $meseroSedeB = User::factory()->create();
    $meseroSedeB->accesos()->create(['empresa_id' => $empresa->id, 'sede_id' => $sedeB->id, 'rol' => Rol::Mesero]);

    $this->actingAs($adminSedeA)
        ->get("/admin/{$empresa->slug}/accesos")
        ->assertOk()
        ->assertDontSee($meseroSedeB->email)
        ->assertDontSee($adminCentral->email);
});

it('la base de datos rechaza duplicar el mismo usuario+sede+rol, no solo el formulario', function () {
    [$empresa, $sedes] = crearEmpresaConAdminCentralYSedes('Empresa Constraint Acceso', 1);
    $sede = $sedes->first();

    $mesero = User::factory()->create();
    $mesero->accesos()->create(['empresa_id' => $empresa->id, 'sede_id' => $sede->id, 'rol' => Rol::Mesero]);

    expect(fn () => Acceso::create([
        'user_id' => $mesero->id,
        'empresa_id' => $empresa->id,
        'sede_id' => $sede->id,
        'rol' => Rol::Mesero,
    ]))->toThrow(QueryException::class);
});

it('la base de datos rechaza duplicar administración central del mismo usuario en la misma empresa', function () {
    [$empresa, , $admin] = crearEmpresaConAdminCentralYSedes('Empresa Constraint Admin Central', 1);

    expect(fn () => Acceso::create([
        'user_id' => $admin->id,
        'empresa_id' => $empresa->id,
        'sede_id' => null,
        'rol' => Rol::AdministracionCentral,
    ]))->toThrow(QueryException::class);
});

it('el formulario de edición viene prellenado con el perfil actual del usuario', function () {
    [$empresa, $sedes, $admin] = crearEmpresaConAdminCentralYSedes('Empresa Editar Perfil Prellenado');
    $sede = $sedes->first();

    $usuario = User::factory()->create(['name' => 'Ana Mesera', 'email' => 'ana@dulcita.test', 'telefono' => '3001234567']);
    $acceso = $usuario->accesos()->create(['empresa_id' => $empresa->id, 'sede_id' => $sede->id, 'rol' => Rol::Mesero]);

    $this->actingAs($admin);
    $this->get("/admin/{$empresa->slug}/accesos");

    Livewire::test(ListAccesos::class)
        ->mountTableAction('edit', $acceso)
        ->assertTableActionDataSet([
            'user_name' => 'Ana Mesera',
            'user_email' => 'ana@dulcita.test',
            'user_telefono' => '3001234567',
        ]);
});

it('editar un acceso permite corregir el nombre, correo, teléfono y documento del usuario', function () {
    [$empresa, $sedes, $admin] = crearEmpresaConAdminCentralYSedes('Empresa Editar Perfil Usuario');
    $sede = $sedes->first();

    $usuario = User::factory()->create(['name' => 'Nombre Viejo', 'email' => 'viejo@dulcita.test']);
    $acceso = $usuario->accesos()->create(['empresa_id' => $empresa->id, 'sede_id' => $sede->id, 'rol' => Rol::Mesero]);

    $this->actingAs($admin);
    $this->get("/admin/{$empresa->slug}/accesos");

    Livewire::test(ListAccesos::class)
        ->mountTableAction('edit', $acceso)
        ->setTableActionData([
            'roles' => ['mesero'],
            'sede_id' => $sede->id,
            'user_name' => 'Nombre Nuevo',
            'user_email' => 'nuevo@dulcita.test',
            'user_telefono' => '3009876543',
            'user_documento_identidad' => '123456789',
        ])
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    $usuario->refresh();
    expect($usuario->name)->toBe('Nombre Nuevo');
    expect($usuario->email)->toBe('nuevo@dulcita.test');
    expect($usuario->telefono)->toBe('3009876543');
    expect($usuario->documento_identidad)->toBe('123456789');
});

it('rechaza cambiar el correo a uno que ya usa otro usuario', function () {
    [$empresa, $sedes, $admin] = crearEmpresaConAdminCentralYSedes('Empresa Editar Correo Duplicado');
    $sede = $sedes->first();

    User::factory()->create(['email' => 'ocupado@dulcita.test']);
    $usuario = User::factory()->create(['email' => 'libre@dulcita.test']);
    $acceso = $usuario->accesos()->create(['empresa_id' => $empresa->id, 'sede_id' => $sede->id, 'rol' => Rol::Mesero]);

    $this->actingAs($admin);
    $this->get("/admin/{$empresa->slug}/accesos");

    Livewire::test(ListAccesos::class)
        ->mountTableAction('edit', $acceso)
        ->setTableActionData([
            'roles' => ['mesero'],
            'sede_id' => $sede->id,
            'user_name' => $usuario->name,
            'user_email' => 'ocupado@dulcita.test',
        ])
        ->callMountedTableAction()
        ->assertHasTableActionErrors(['user_email']);

    expect($usuario->fresh()->email)->toBe('libre@dulcita.test');
});
