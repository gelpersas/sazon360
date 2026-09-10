<?php

use App\Enums\Rol;
use App\Models\AreaPreparacion;
use App\Models\Categoria;
use App\Models\Empresa;
use App\Models\Mesa;
use App\Models\Producto;
use App\Models\Sede;
use App\Models\User;

/**
 * Cubre la segregación de roles dentro del POS táctil (Mesero/Caja/Área de
 * preparación) — antes de esto, cualquier rol operativo con acceso a la
 * sede podía hacer cualquier acción (abrir caja, cobrar, avanzar comandas)
 * sin importar su rol real. Ver docs/DECISIONES.md.
 */
function crearEscenarioPos(string $nombreEmpresa): array
{
    $empresa = Empresa::factory()->create(['nombre' => $nombreEmpresa]);
    $sede = Sede::factory()->for($empresa)->create();
    $area = AreaPreparacion::factory()->for($empresa)->for($sede)->create();
    $mesa = Mesa::factory()->for($empresa)->for($sede)->create();
    $categoria = Categoria::factory()->for($empresa)->create();
    $producto = Producto::factory()->for($empresa)->for($categoria)->create(['precio' => '10.00']);

    return [$empresa, $sede, $area, $mesa, $producto];
}

function crearUsuarioConRolEnSede(Empresa $empresa, Sede $sede, Rol $rol): User
{
    $usuario = User::factory()->create();
    $usuario->accesos()->create([
        'empresa_id' => $empresa->id,
        'sede_id' => $sede->id,
        'rol' => $rol,
    ]);

    return $usuario;
}

it('un mesero puede tomar un pedido y enviarlo a cocina, pero no puede cobrarlo', function () {
    [$empresa, $sede, $area, , $producto] = crearEscenarioPos('Empresa Mesero Toma Pedido');
    $mesero = crearUsuarioConRolEnSede($empresa, $sede, Rol::Mesero);

    $this->actingAs($mesero);

    $pedido = $this->postJson("/api/pos/sedes/{$sede->id}/pedidos", [
        'tipo' => 'mostrador',
        'idempotency_key' => 'mesero-pedido-1',
    ])->assertOk()->json('data');

    $this->postJson("/api/pos/pedidos/{$pedido['id']}/items", [
        'producto_id' => $producto->id,
        'area_preparacion_id' => $area->id,
        'cantidad' => 1,
    ])->assertOk();

    $this->postJson("/api/pos/pedidos/{$pedido['id']}/enviar-comanda")->assertOk();

    $this->postJson("/api/pos/pedidos/{$pedido['id']}/pagos", [
        'medio' => 'efectivo',
        'monto' => '10.00',
        'idempotency_key' => 'mesero-pago-1',
    ])->assertForbidden();
});

it('un mesero no puede abrir, ver ni cerrar la caja de su sede', function () {
    [$empresa, $sede] = crearEscenarioPos('Empresa Mesero Caja');
    $mesero = crearUsuarioConRolEnSede($empresa, $sede, Rol::Mesero);

    $this->actingAs($mesero);

    $this->getJson("/api/pos/sedes/{$sede->id}/caja")->assertForbidden();
    $this->postJson("/api/pos/sedes/{$sede->id}/caja/abrir", ['monto_inicial' => '20.00'])->assertForbidden();
});

it('un mesero no puede ver ni avanzar comandas en cocina (KDS)', function () {
    [$empresa, $sede] = crearEscenarioPos('Empresa Mesero Cocina');
    $mesero = crearUsuarioConRolEnSede($empresa, $sede, Rol::Mesero);

    $this->actingAs($mesero);

    $this->getJson("/api/pos/sedes/{$sede->id}/comandas")->assertForbidden();
});

it('un cajero puede cobrar y manejar la caja, pero no puede avanzar comandas en cocina', function () {
    [$empresa, $sede, $area, , $producto] = crearEscenarioPos('Empresa Cajero Cocina');
    $cajero = crearUsuarioConRolEnSede($empresa, $sede, Rol::Caja);

    $this->actingAs($cajero);

    $this->getJson("/api/pos/sedes/{$sede->id}/comandas")->assertForbidden();

    $pedido = $this->postJson("/api/pos/sedes/{$sede->id}/pedidos", [
        'tipo' => 'mostrador',
        'idempotency_key' => 'cajero-pedido-1',
    ])->json('data');

    $this->postJson("/api/pos/pedidos/{$pedido['id']}/items", [
        'producto_id' => $producto->id,
        'area_preparacion_id' => $area->id,
        'cantidad' => 1,
    ])->assertOk();

    $this->postJson("/api/pos/pedidos/{$pedido['id']}/pagos", [
        'medio' => 'efectivo',
        'monto' => '10.00',
        'idempotency_key' => 'cajero-pago-1',
    ])->assertOk()->assertJsonPath('data.estado', 'cobrado');
});

it('un área de preparación solo puede ver y avanzar comandas — no puede tomar pedidos ni manejar caja', function () {
    [$empresa, $sede, $area, , $producto] = crearEscenarioPos('Empresa Cocina Aislada');
    $cocinero = crearUsuarioConRolEnSede($empresa, $sede, Rol::AreaPreparacion);

    // El pedido y su comanda los crea un cajero — el cocinero solo debe
    // poder actuar sobre la comanda ya enviada, nunca crear el pedido.
    $cajero = crearUsuarioConRolEnSede($empresa, $sede, Rol::Caja);
    $this->actingAs($cajero);

    $pedido = $this->postJson("/api/pos/sedes/{$sede->id}/pedidos", [
        'tipo' => 'mostrador',
        'idempotency_key' => 'cocina-pedido-1',
    ])->json('data');

    $this->postJson("/api/pos/pedidos/{$pedido['id']}/items", [
        'producto_id' => $producto->id,
        'area_preparacion_id' => $area->id,
        'cantidad' => 1,
    ]);

    $comandaId = $this->postJson("/api/pos/pedidos/{$pedido['id']}/enviar-comanda")
        ->json('comandas_creadas.0');

    $this->actingAs($cocinero);

    $this->postJson("/api/pos/sedes/{$sede->id}/pedidos", [
        'tipo' => 'mostrador',
        'idempotency_key' => 'cocina-pedido-intento',
    ])->assertForbidden();

    $this->getJson("/api/pos/sedes/{$sede->id}/caja")->assertForbidden();
    $this->postJson("/api/pos/sedes/{$sede->id}/caja/abrir", ['monto_inicial' => '20.00'])->assertForbidden();

    $this->getJson("/api/pos/sedes/{$sede->id}/comandas")
        ->assertOk()
        ->assertJsonPath('data.0.id', $comandaId);

    $this->postJson("/api/pos/comandas/{$comandaId}/avanzar")
        ->assertOk()
        ->assertJsonPath('data.estado', 'en_preparacion');
});

it('administración de sede tiene acceso a mostrador, cocina y caja en el POS pese a los límites de los roles operativos', function () {
    [$empresa, $sede, $area, , $producto] = crearEscenarioPos('Empresa Admin Sede Pos');
    $admin = crearUsuarioConRolEnSede($empresa, $sede, Rol::AdministracionSede);

    $this->actingAs($admin);

    $this->getJson("/api/pos/sedes/{$sede->id}/caja")->assertOk();
    $this->getJson("/api/pos/sedes/{$sede->id}/comandas")->assertOk();

    $pedido = $this->postJson("/api/pos/sedes/{$sede->id}/pedidos", [
        'tipo' => 'mostrador',
        'idempotency_key' => 'admin-sede-pedido-1',
    ])->assertOk()->json('data');

    $this->postJson("/api/pos/pedidos/{$pedido['id']}/items", [
        'producto_id' => $producto->id,
        'area_preparacion_id' => $area->id,
        'cantidad' => 1,
    ])->assertOk();

    $this->postJson("/api/pos/pedidos/{$pedido['id']}/pagos", [
        'medio' => 'efectivo',
        'monto' => '10.00',
        'idempotency_key' => 'admin-sede-pago-1',
    ])->assertOk();
});

/**
 * Un usuario puede tener más de un `Acceso` en la misma sede (el
 * formulario del panel siempre lo permitió — el hueco real estaba en que
 * `User::rolEnSede()` solo devolvía el primero, así que el segundo rol
 * quedaba ignorado en silencio por todos los permisos del POS). Ver
 * User::rolesEnSede()/accedeXEnSede().
 */
it('un usuario con Mesero y Caja a la vez puede tomar el pedido y también cobrarlo', function () {
    [$empresa, $sede, $area, , $producto] = crearEscenarioPos('Empresa Multi Rol Mesero Caja');

    $usuario = User::factory()->create();
    $usuario->accesos()->create(['empresa_id' => $empresa->id, 'sede_id' => $sede->id, 'rol' => Rol::Mesero]);
    $usuario->accesos()->create(['empresa_id' => $empresa->id, 'sede_id' => $sede->id, 'rol' => Rol::Caja]);

    $this->actingAs($usuario);

    // Dominio de Mesero (tomar pedido) — ya funcionaba, pero confirma que
    // agregar un segundo rol no le quita el primero.
    $pedido = $this->postJson("/api/pos/sedes/{$sede->id}/pedidos", [
        'tipo' => 'mostrador', 'idempotency_key' => 'multi-rol-pedido-1',
    ])->assertOk()->json('data');

    $this->postJson("/api/pos/pedidos/{$pedido['id']}/items", [
        'producto_id' => $producto->id, 'area_preparacion_id' => $area->id, 'cantidad' => 1,
    ])->assertOk();

    // Dominio de Caja (cobrar) — antes de la corrección, esto daba 403
    // porque rolEnSede() devolvía "mesero" (el primer Acceso creado) y
    // nunca llegaba a considerar el segundo ("caja").
    $this->postJson("/api/pos/pedidos/{$pedido['id']}/pagos", [
        'medio' => 'efectivo', 'monto' => '10.00', 'idempotency_key' => 'multi-rol-pago-1',
    ])->assertOk()->assertJsonPath('data.estado', 'cobrado');
});

it('un usuario con Mesero y Área de preparación a la vez puede tomar el pedido y también avanzar la comanda', function () {
    [$empresa, $sede, $area, , $producto] = crearEscenarioPos('Empresa Multi Rol Mesero Cocina');

    $usuario = User::factory()->create();
    $usuario->accesos()->create(['empresa_id' => $empresa->id, 'sede_id' => $sede->id, 'rol' => Rol::Mesero]);
    $usuario->accesos()->create(['empresa_id' => $empresa->id, 'sede_id' => $sede->id, 'rol' => Rol::AreaPreparacion]);

    $this->actingAs($usuario);

    $pedido = $this->postJson("/api/pos/sedes/{$sede->id}/pedidos", [
        'tipo' => 'mostrador', 'idempotency_key' => 'multi-rol-cocina-pedido-1',
    ])->json('data');

    $this->postJson("/api/pos/pedidos/{$pedido['id']}/items", [
        'producto_id' => $producto->id, 'area_preparacion_id' => $area->id, 'cantidad' => 1,
    ]);

    $comandaId = $this->postJson("/api/pos/pedidos/{$pedido['id']}/enviar-comanda")
        ->json('comandas_creadas.0');

    $this->postJson("/api/pos/comandas/{$comandaId}/avanzar")
        ->assertOk()
        ->assertJsonPath('data.estado', 'en_preparacion');
});

it('User::rolesEnSede() devuelve todos los roles del usuario en esa sede, no solo el primero', function () {
    [$empresa, $sede] = crearEscenarioPos('Empresa RolesEnSede');

    $usuario = User::factory()->create();
    $usuario->accesos()->create(['empresa_id' => $empresa->id, 'sede_id' => $sede->id, 'rol' => Rol::Mesero]);
    $usuario->accesos()->create(['empresa_id' => $empresa->id, 'sede_id' => $sede->id, 'rol' => Rol::Caja]);

    $roles = $usuario->rolesEnSede($sede);

    expect($roles)->toHaveCount(2);
    expect($roles->pluck('value')->all())->toEqualCanonicalizing(['mesero', 'caja']);
});
