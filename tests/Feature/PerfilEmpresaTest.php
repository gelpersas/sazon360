<?php

use App\Enums\Rol;
use App\Filament\Pages\Tenancy\EditEmpresaProfile;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function crearEmpresaYAdminParaPerfil(string $nombreEmpresa): array
{
    $empresa = Empresa::factory()->create(['nombre' => $nombreEmpresa]);

    $admin = User::factory()->create();
    $admin->accesos()->create([
        'empresa_id' => $empresa->id,
        'sede_id' => null,
        'rol' => Rol::AdministracionCentral,
    ]);

    return [$empresa, $admin];
}

it('el NIT no se puede modificar una vez creado', function () {
    [$empresa] = crearEmpresaYAdminParaPerfil('Empresa NIT Fijo');

    $empresa->update(['nit' => '900123456', 'dv' => '7']);

    expect(fn () => $empresa->update(['nit' => '900999999']))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => $empresa->update(['dv' => '3']))
        ->toThrow(InvalidArgumentException::class);

    expect($empresa->fresh()->nit)->toBe('900123456');
    expect($empresa->fresh()->dv)->toBe('7');
});

it('se puede fijar el NIT por primera vez y seguir editando otros campos después', function () {
    [$empresa] = crearEmpresaYAdminParaPerfil('Empresa NIT Primera Vez');

    $empresa->update(['nit' => '900123456', 'dv' => '7']);

    expect($empresa->fresh()->nit)->toBe('900123456');

    // Otros campos siguen editables normalmente después de fijar el NIT.
    $empresa->update(['direccion' => 'Calle 10 # 20-30', 'telefono' => '3001234567']);

    expect($empresa->fresh()->direccion)->toBe('Calle 10 # 20-30');
});

it('actualiza el perfil de empresa desde el panel de Filament, incluido el logo', function () {
    Storage::fake('public');

    [$empresa, $admin] = crearEmpresaYAdminParaPerfil('Empresa Perfil Panel');

    $this->actingAs($admin);
    $this->get("/admin/{$empresa->slug}/profile");

    $logo = UploadedFile::fake()->image('logo.png');

    Livewire::test(EditEmpresaProfile::class)
        ->fillForm([
            'nombre' => $empresa->nombre,
            'nombre_comercial' => 'Pastelería Dulcita',
            'nit' => '900123456',
            'dv' => '7',
            'regimen_tributario' => 'Régimen común',
            'actividad_economica_ciiu' => '5610',
            'direccion' => 'Calle 10 # 20-30',
            'telefono' => '3001234567',
            'whatsapp' => '3001234567',
            'email' => 'contacto@dulcita.test',
            'logo_path' => $logo,
            'estado' => 'activa',
            'notas_internas' => 'Cliente piloto',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $empresa->refresh();

    expect($empresa->nombre_comercial)->toBe('Pastelería Dulcita');
    expect($empresa->nit)->toBe('900123456');
    expect($empresa->dv)->toBe('7');
    expect($empresa->email)->toBe('contacto@dulcita.test');
    expect($empresa->logo_path)->not->toBeNull();

    Storage::disk('public')->assertExists($empresa->logo_path);
});

it('el formulario de perfil deshabilita el NIT y el DV una vez que ya están fijados', function () {
    [$empresa, $admin] = crearEmpresaYAdminParaPerfil('Empresa NIT Deshabilitado');

    $empresa->update(['nit' => '900123456', 'dv' => '7']);

    $this->actingAs($admin);
    $this->get("/admin/{$empresa->slug}/profile");

    Livewire::test(EditEmpresaProfile::class)
        ->assertFormFieldIsDisabled('nit')
        ->assertFormFieldIsDisabled('dv');
});
