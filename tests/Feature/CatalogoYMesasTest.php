<?php

use App\Enums\Rol;
use App\Enums\TipoPedido;
use App\Filament\Resources\Mesas\Pages\ListMesas;
use App\Filament\Resources\Sedes\SedeResource;
use App\Filament\Support\BorradoSeguro;
use App\Models\AreaPreparacion;
use App\Models\Categoria;
use App\Models\Empresa;
use App\Models\ItemPedido;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Sede;
use App\Models\User;
use App\Policies\CategoriaPolicy;
use App\Policies\MesaPolicy;
use App\Policies\ProductoPolicy;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function crearEmpresaConSedeYRoles(string $nombreEmpresa): array
{
    $empresa = Empresa::factory()->create(['nombre' => $nombreEmpresa]);
    $sede = Sede::factory()->for($empresa)->create();

    $adminCentral = User::factory()->create();
    $adminCentral->accesos()->create([
        'empresa_id' => $empresa->id,
        'sede_id' => null,
        'rol' => Rol::AdministracionCentral,
    ]);

    $adminSede = User::factory()->create();
    $adminSede->accesos()->create([
        'empresa_id' => $empresa->id,
        'sede_id' => $sede->id,
        'rol' => Rol::AdministracionSede,
    ]);

    return [$empresa, $sede, $adminCentral, $adminSede];
}

it('el listado de productos de Filament solo muestra productos del tenant actual', function () {
    [$empresaA, , $adminA] = crearEmpresaConSedeYRoles('Empresa Catálogo A');
    [$empresaB] = crearEmpresaConSedeYRoles('Empresa Catálogo B');

    $categoriaA = Categoria::factory()->for($empresaA)->create();
    $productoA = Producto::factory()->for($empresaA)->for($categoriaA)->create();

    $categoriaB = Categoria::factory()->for($empresaB)->create();
    $productoB = Producto::factory()->for($empresaB)->for($categoriaB)->create();

    $this->actingAs($adminA)
        ->get("/admin/{$empresaA->slug}/productos")
        ->assertOk()
        ->assertSee($productoA->nombre)
        ->assertDontSee($productoB->nombre);
});

it('solo administración central puede crear o eliminar productos y categorías', function () {
    [$empresa, , $adminCentral, $adminSede] = crearEmpresaConSedeYRoles('Empresa Roles Catálogo');

    Filament::setTenant($empresa, isQuiet: true);

    $categoriaPolicy = new CategoriaPolicy;
    $productoPolicy = new ProductoPolicy;

    expect($categoriaPolicy->create($adminCentral))->toBeTrue();
    expect($categoriaPolicy->create($adminSede))->toBeFalse();

    expect($productoPolicy->create($adminCentral))->toBeTrue();
    expect($productoPolicy->create($adminSede))->toBeFalse();
});

it('administración de sede puede ver pero no editar el catálogo', function () {
    [$empresa, , , $adminSede] = crearEmpresaConSedeYRoles('Empresa Solo Lectura');

    $categoria = Categoria::factory()->for($empresa)->create();
    $producto = Producto::factory()->for($empresa)->for($categoria)->create();

    $productoPolicy = new ProductoPolicy;

    expect($productoPolicy->view($adminSede, $producto))->toBeTrue();
    expect($productoPolicy->update($adminSede, $producto))->toBeFalse();
});

it('administración de sede solo ve y gestiona las mesas de su propia sede', function () {
    [$empresa, $sedeA, $adminCentral, $adminSedeA] = crearEmpresaConSedeYRoles('Empresa Mesas Multisede');
    $sedeB = Sede::factory()->for($empresa)->create();

    $mesaA = Mesa::factory()->for($empresa)->for($sedeA)->create();
    $mesaB = Mesa::factory()->for($empresa)->for($sedeB)->create();

    $response = $this->actingAs($adminSedeA)
        ->get("/admin/{$empresa->slug}/mesas")
        ->assertOk();

    $response->assertSee($mesaA->nombre);
    $response->assertDontSee($mesaB->nombre);

    // Administración central sí ve las mesas de ambas sedes.
    $this->actingAs($adminCentral)
        ->get("/admin/{$empresa->slug}/mesas")
        ->assertOk()
        ->assertSee($mesaA->nombre)
        ->assertSee($mesaB->nombre);
});

it('el modelo Mesa rechaza guardarse en una sede que el usuario autenticado no administra', function () {
    [$empresa, , , $adminSedeA] = crearEmpresaConSedeYRoles('Empresa Mesa Ajena');
    $sedeB = Sede::factory()->for($empresa)->create();

    $this->actingAs($adminSedeA);

    expect(function () use ($empresa, $sedeB) {
        Mesa::create([
            'empresa_id' => $empresa->id,
            'sede_id' => $sedeB->id,
            'nombre' => 'Mesa intrusa',
            'estado' => 'activa',
        ]);
    })->toThrow(AuthorizationException::class);
});

it('la policy de mesas permite a un admin de sede gestionar (incl. eliminar) las mesas de su propia sede', function () {
    [$empresa, $sedeA, , $adminSedeA] = crearEmpresaConSedeYRoles('Empresa Permisos Mesa');
    $sedeB = Sede::factory()->for($empresa)->create();

    $mesaPropia = Mesa::factory()->for($empresa)->for($sedeA)->create();
    $mesaAjena = Mesa::factory()->for($empresa)->for($sedeB)->create();

    $policy = new MesaPolicy;

    expect($policy->update($adminSedeA, $mesaPropia))->toBeTrue();
    expect($policy->delete($adminSedeA, $mesaPropia))->toBeTrue();

    expect($policy->update($adminSedeA, $mesaAjena))->toBeFalse();
    expect($policy->delete($adminSedeA, $mesaAjena))->toBeFalse();
});

it('borrar una categoría con productos asociados no la elimina ni lanza un error crudo', function () {
    [$empresa] = crearEmpresaConSedeYRoles('Empresa Borrado Seguro');

    $categoria = Categoria::factory()->for($empresa)->create();
    Producto::factory()->for($empresa)->for($categoria)->create();

    BorradoSeguro::variosRegistros(new Collection([$categoria]), 'categorías');

    expect(Categoria::find($categoria->id))->not->toBeNull();
});

it('borrar en bloque una mesa con pedidos asociados no la elimina ni lanza un error crudo', function () {
    [$empresa, $sede, $adminCentral] = crearEmpresaConSedeYRoles('Empresa Borrado Seguro Mesa');

    $mesa = Mesa::factory()->for($empresa)->for($sede)->create();
    Pedido::abrir($sede, $adminCentral, TipoPedido::Mesa, $mesa, 'borrado-seguro-mesa');

    BorradoSeguro::variosRegistros(new Collection([$mesa]), 'mesas');

    expect(Mesa::find($mesa->id))->not->toBeNull();
});

it('borrar en bloque un producto ya vendido no lo elimina ni lanza un error crudo', function () {
    [$empresa, $sede, $adminCentral] = crearEmpresaConSedeYRoles('Empresa Borrado Seguro Producto');

    $categoria = Categoria::factory()->for($empresa)->create();
    $producto = Producto::factory()->for($empresa)->for($categoria)->create();
    $area = AreaPreparacion::factory()->for($empresa)->for($sede)->create();
    $pedido = Pedido::abrir($sede, $adminCentral, TipoPedido::Mostrador, null, 'borrado-seguro-producto');

    ItemPedido::create([
        'pedido_id' => $pedido->id,
        'producto_id' => $producto->id,
        'area_preparacion_id' => $area->id,
        'nombre_producto' => $producto->nombre,
        'precio_unitario' => $producto->precio,
        'cantidad' => 1,
    ]);

    BorradoSeguro::variosRegistros(new Collection([$producto]), 'productos');

    expect(Producto::find($producto->id))->not->toBeNull();
});

it('el formulario de mesa rechaza un nombre repetido dentro de la misma sede', function () {
    [$empresa, $sedeA, $adminCentral] = crearEmpresaConSedeYRoles('Empresa Nombres Mesa');
    $sedeB = Sede::factory()->for($empresa)->create();

    Mesa::factory()->for($empresa)->for($sedeA)->create(['nombre' => 'Mesa 1']);

    $this->actingAs($adminCentral);
    // Visitar la ruta real primero deja establecido el panel/tenant vía el
    // middleware de Filament — Filament::setTenant() a solas no alcanza para
    // que el modelo asocie empresa_id automáticamente al crear.
    $this->get("/admin/{$empresa->slug}/mesas");

    // "Crear mesa" ahora es un modal (ver docs/DECISIONES.md DEC-041), no
    // una página aparte.
    Livewire::test(ListMesas::class)
        ->mountAction('create')
        ->setActionData([
            'sede_id' => $sedeA->id,
            'nombre' => 'Mesa 1',
            'estado' => true, // Toggle (switch), no el string guardado en base de datos — ver DEC-052.
        ])
        ->callMountedAction()
        ->assertHasActionErrors(['nombre']);

    Livewire::test(ListMesas::class)
        ->mountAction('create')
        ->setActionData([
            'sede_id' => $sedeB->id,
            'nombre' => 'Mesa 1',
            'estado' => true, // Toggle (switch), no el string guardado en base de datos — ver DEC-052.
        ])
        ->callMountedAction()
        ->assertHasNoActionErrors();
});

it('"Sedes" solo se registra en el menú para administración central, no para administración de sede', function () {
    // Prueba SedeResource::shouldRegisterNavigation() directo, no vía
    // get()+assertSee(): Filament resuelve/memoriza qué Resources "deben
    // registrarse" en el menú a nivel de todo el proceso (no por request),
    // así que dos llamadas HTTP dentro de un mismo run de tests pueden
    // reusar la respuesta que dio el primer usuario evaluado — en
    // producción cada request real arranca la app de cero, así que ese
    // efecto no existe ahí, pero sí puede colarse en un test que dependa de
    // dos get() sucesivos. Llamar al método directamente evita ese ruido y
    // prueba lo que realmente importa: la condición en sí.
    [$empresa, , $adminCentral, $adminSede] = crearEmpresaConSedeYRoles('Empresa Menú Sedes');

    Filament::setTenant($empresa, isQuiet: true);

    Auth::login($adminCentral);
    expect(SedeResource::shouldRegisterNavigation())->toBeTrue();

    Auth::login($adminSede);
    expect(SedeResource::shouldRegisterNavigation())->toBeFalse();
});

it('ocultar "Sedes" del menú para administración de sede no bloquea el link directo', function () {
    [$empresa, , , $adminSede] = crearEmpresaConSedeYRoles('Empresa Sedes Link Directo');

    $this->actingAs($adminSede)
        ->get("/admin/{$empresa->slug}/sedes")
        ->assertOk();
});

it('un producto sin imagen expone imagen_url en null; con imagen, la URL pública del disco', function () {
    Storage::fake('public');

    [$empresa] = crearEmpresaConSedeYRoles('Empresa Imagen Producto');
    $categoria = Categoria::factory()->for($empresa)->create();

    $sinImagen = Producto::factory()->for($empresa)->for($categoria)->create(['imagen_path' => null]);
    expect($sinImagen->imagen_url)->toBeNull();

    $archivo = UploadedFile::fake()->image('empanada.jpg');
    $ruta = $archivo->store('imagenes-productos', 'public');
    $conImagen = Producto::factory()->for($empresa)->for($categoria)->create(['imagen_path' => $ruta]);

    // asset() a propósito (no Storage::url()/APP_URL) — ver el docblock de
    // Producto::imagenUrl().
    expect($conImagen->imagen_url)->toBe(asset("storage/{$ruta}"));
});

it('el catálogo que consume el POS incluye imagen_url y descripción de cada producto', function () {
    Storage::fake('public');

    [$empresa, $sede] = crearEmpresaConSedeYRoles('Empresa Catalogo Pos Imagen');
    $categoria = Categoria::factory()->for($empresa)->create(['estado' => 'activa']);

    $ruta = UploadedFile::fake()->image('cafe.jpg')->store('imagenes-productos', 'public');
    $producto = Producto::factory()->for($empresa)->for($categoria)->create([
        'nombre' => 'Café americano',
        'descripcion' => 'Café negro recién hecho',
        'estado' => 'activo',
        'imagen_path' => $ruta,
    ]);

    $mesero = User::factory()->create();
    $mesero->accesos()->create(['empresa_id' => $empresa->id, 'sede_id' => $sede->id, 'rol' => Rol::Mesero]);

    $this->actingAs($mesero)
        ->getJson("/api/pos/sedes/{$sede->id}/catalogo")
        ->assertOk()
        ->assertJsonPath('data.0.productos.0.id', $producto->id)
        ->assertJsonPath('data.0.productos.0.descripcion', 'Café negro recién hecho')
        ->assertJsonPath('data.0.productos.0.imagen_url', asset("storage/{$ruta}"));
});
