<?php

use App\Enums\EstadoFactura;
use App\Enums\EstadoPedido;
use App\Enums\MedioPago;
use App\Enums\Rol;
use App\Enums\TipoDocumentoCliente;
use App\Enums\TipoPedido;
use App\Filament\Resources\Clientes\Pages\ListClientes;
use App\Models\AreaPreparacion;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\FacturaElectronica;
use App\Models\ItemPedido;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Sede;
use App\Models\User;
use App\Policies\ClientePolicy;
use App\Services\Facturacion\FactusProveedor;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

function crearEmpresaConAdminYSedeParaClientes(string $nombreEmpresa): array
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

it('solo administración central puede crear o eliminar clientes', function () {
    [$empresa, $sede, $adminCentral] = crearEmpresaConAdminYSedeParaClientes('Empresa Permiso Cliente');

    $cajero = User::factory()->create();
    $cajero->accesos()->create(['empresa_id' => $empresa->id, 'sede_id' => $sede->id, 'rol' => Rol::Caja]);

    Filament::setTenant($empresa, isQuiet: true);
    $policy = new ClientePolicy;

    expect($policy->create($adminCentral))->toBeTrue();
    expect($policy->create($cajero))->toBeFalse();
});

it('el listado de clientes de Filament respeta el aislamiento por tenant', function () {
    [$empresaA, , $adminA] = crearEmpresaConAdminYSedeParaClientes('Empresa Aislamiento Cliente A');
    [$empresaB] = crearEmpresaConAdminYSedeParaClientes('Empresa Aislamiento Cliente B');

    Cliente::create([
        'empresa_id' => $empresaA->id, 'tipo_persona' => 'natural', 'tipo_documento' => '13',
        'numero_documento' => '111', 'nombres' => 'Cliente', 'apellidos' => 'Exclusivo A', 'estado' => 'activo',
    ]);
    Cliente::create([
        'empresa_id' => $empresaB->id, 'tipo_persona' => 'natural', 'tipo_documento' => '13',
        'numero_documento' => '222', 'nombres' => 'Cliente', 'apellidos' => 'Exclusivo B', 'estado' => 'activo',
    ]);

    $this->actingAs($adminA)
        ->get("/admin/{$empresaA->slug}/clientes")
        ->assertOk()
        ->assertSee('Cliente Exclusivo A')
        ->assertDontSee('Cliente Exclusivo B');

    $this->actingAs($adminA)
        ->get("/admin/{$empresaB->slug}/clientes")
        ->assertNotFound();
});

/**
 * Bug real encontrado el 2026-09-08: `tipo_documento` usaba un array plano
 * de opciones (['13' => ..., '31' => 'NIT', ...]) — PHP convierte
 * automáticamente esas claves a ENTEROS (son cadenas numéricas
 * "canónicas"), así que `$get('tipo_documento')` devolvía el entero 31 en
 * vez del string '31', y la comparación `=== '31'` del campo "DV" nunca
 * daba true — el campo jamás aparecía, ni al crear ni al editar. Corregido
 * con un enum de verdad (`App\Enums\TipoDocumentoCliente`) en vez del
 * array — ver docs/DECISIONES.md.
 */
it('el campo DV aparece al elegir NIT como tipo de documento', function () {
    [$empresa, , $admin] = crearEmpresaConAdminYSedeParaClientes('Empresa DV Visible Crear');

    $this->actingAs($admin);
    $this->get("/admin/{$empresa->slug}/clientes");

    Livewire::test(ListClientes::class)
        ->mountAction('create')
        ->assertSchemaComponentHidden('dv')
        ->setActionData(['tipo_documento' => TipoDocumentoCliente::NIT->value])
        ->assertSchemaComponentVisible('dv');
});

it('el campo DV aparece automáticamente al editar un cliente que ya tiene NIT', function () {
    [$empresa, , $admin] = crearEmpresaConAdminYSedeParaClientes('Empresa DV Visible Editar');

    $cliente = Cliente::create([
        'empresa_id' => $empresa->id, 'tipo_persona' => 'juridica', 'tipo_documento' => '31',
        'numero_documento' => '900123456', 'dv' => '7', 'razon_social' => 'Empresa Cliente SAS', 'estado' => 'activo',
    ]);

    $this->actingAs($admin);
    $this->get("/admin/{$empresa->slug}/clientes");

    Livewire::test(ListClientes::class)
        ->mountTableAction('edit', $cliente)
        ->assertSchemaComponentVisible('dv')
        ->assertTableActionDataSet(['dv' => '7']);
});

it('no se puede registrar dos veces el mismo tipo y número de documento en la misma empresa', function () {
    [$empresa, , $admin] = crearEmpresaConAdminYSedeParaClientes('Empresa Cliente Duplicado');

    $this->actingAs($admin);
    $this->get("/admin/{$empresa->slug}/clientes");

    // 'tipo_persona' y 'estado' ahora son Toggle (switch) — su estado
    // interno es booleano (false = natural/inactivo, true = jurídica/
    // activo), no el string que se guarda en base de datos — ver DEC-052.
    Livewire::test(ListClientes::class)
        ->mountAction('create')
        ->setActionData([
            'tipo_persona' => false,
            'tipo_documento' => '13',
            'numero_documento' => '900123456',
            'nombres' => 'Primer',
            'apellidos' => 'Cliente',
            'estado' => true,
        ])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    Livewire::test(ListClientes::class)
        ->mountAction('create')
        ->setActionData([
            'tipo_persona' => false,
            'tipo_documento' => '13',
            'numero_documento' => '900123456',
            'nombres' => 'Segundo',
            'apellidos' => 'Cliente, mismo documento',
            'estado' => true,
        ])
        ->callMountedAction()
        ->assertHasActionErrors(['numero_documento']);
});

it('Pedido::asignarCliente() solo funciona mientras el pedido está abierto', function () {
    [$empresa, $sede, $admin] = crearEmpresaConAdminYSedeParaClientes('Empresa Asignar Cliente');
    $cliente = Cliente::create([
        'empresa_id' => $empresa->id, 'tipo_persona' => 'natural', 'tipo_documento' => '13',
        'numero_documento' => '333', 'nombres' => 'Juan', 'apellidos' => 'Pérez', 'estado' => 'activo',
    ]);

    $pedido = Pedido::abrir($sede, $admin, TipoPedido::Mostrador, null, 'idem-asignar-cliente-1');
    $pedido->asignarCliente($cliente);

    expect($pedido->fresh()->cliente_id)->toBe($cliente->id);

    $pedido->asignarCliente(null);
    expect($pedido->fresh()->cliente_id)->toBeNull();

    $pedido->update(['estado' => EstadoPedido::Cobrado]);

    expect(fn () => $pedido->asignarCliente($cliente))
        ->toThrow(RuntimeException::class, 'Solo se puede asignar el cliente de un pedido abierto.');
});

it('FactusProveedor factura a nombre del cliente asignado (persona natural) en vez del consumidor final', function () {
    Http::fake([
        '*/oauth/token' => Http::response(['access_token' => 'token-de-prueba'], 200),
        '*/v2/bills/validate' => Http::response([
            'status' => 'Created',
            'data' => ['number' => 'SETP1', 'cufe' => 'cufe-1', 'is_validated' => true],
        ], 201),
    ]);

    [$empresa, $sede, $admin] = crearEmpresaConAdminYSedeParaClientes('Empresa Factus Cliente Natural');
    $producto = Producto::factory()->create(['empresa_id' => $empresa->id, 'precio' => '10.00', 'codigo_impuesto_dian' => '01', 'tasa_iva' => '19.00']);
    $cliente = Cliente::create([
        'empresa_id' => $empresa->id, 'tipo_persona' => 'natural', 'tipo_documento' => '13',
        'numero_documento' => '444', 'nombres' => 'Juan', 'apellidos' => 'Pérez', 'telefono' => '3000000000', 'estado' => 'activo',
    ]);

    $pedido = Pedido::abrir($sede, $admin, TipoPedido::Mostrador, null, 'idem-factus-cliente-natural');
    $pedido->asignarCliente($cliente);

    ItemPedido::create([
        'pedido_id' => $pedido->id,
        'producto_id' => $producto->id,
        'area_preparacion_id' => AreaPreparacion::factory()->for($empresa)->for($sede)->create()->id,
        'nombre_producto' => $producto->nombre,
        'precio_unitario' => $producto->precio,
        'cantidad' => 1,
    ]);

    Pago::create([
        'empresa_id' => $empresa->id, 'pedido_id' => $pedido->id, 'usuario_id' => $admin->id,
        'medio' => MedioPago::Efectivo, 'monto' => '10.00', 'idempotency_key' => 'idem-factus-cliente-pago',
    ]);

    $factura = FacturaElectronica::create([
        'empresa_id' => $empresa->id, 'pedido_id' => $pedido->id, 'proveedor' => 'factus',
        'estado' => EstadoFactura::Pendiente,
    ]);

    $proveedor = new FactusProveedor(
        baseUrl: 'https://api-sandbox.factus.com.co', clientId: 'id', clientSecret: 'secret', email: 'e', password: 'p',
        clienteGenerico: ['identification_document_code' => '13', 'identification' => '222222222222', 'legal_organization_code' => '2', 'names' => 'Consumidor final'],
    );

    $resultado = $proveedor->emitir($factura);

    expect($resultado->exitoso)->toBeTrue();

    Http::assertSent(fn ($request) => $request->url() === 'https://api-sandbox.factus.com.co/v2/bills/validate'
        && $request['customer']['identification_document_code'] === '13'
        && $request['customer']['identification'] === '444'
        && $request['customer']['legal_organization_code'] === '2'
        && $request['customer']['names'] === 'Juan Pérez'
        && $request['customer']['phone'] === '3000000000'
        && ! array_key_exists('company', $request['customer']));
});

it('FactusProveedor factura a nombre de la razón social cuando el cliente es persona jurídica', function () {
    Http::fake([
        '*/oauth/token' => Http::response(['access_token' => 'token-de-prueba'], 200),
        '*/v2/bills/validate' => Http::response([
            'status' => 'Created',
            'data' => ['number' => 'SETP2', 'cufe' => 'cufe-2', 'is_validated' => true],
        ], 201),
    ]);

    [$empresa, $sede, $admin] = crearEmpresaConAdminYSedeParaClientes('Empresa Factus Cliente Juridico');
    $producto = Producto::factory()->create(['empresa_id' => $empresa->id, 'precio' => '10.00', 'codigo_impuesto_dian' => '01', 'tasa_iva' => '19.00']);
    $cliente = Cliente::create([
        'empresa_id' => $empresa->id, 'tipo_persona' => 'juridica', 'tipo_documento' => '31',
        'numero_documento' => '900123456', 'dv' => '7', 'razon_social' => 'Empresa Cliente SAS', 'estado' => 'activo',
    ]);

    $pedido = Pedido::abrir($sede, $admin, TipoPedido::Mostrador, null, 'idem-factus-cliente-juridico');
    $pedido->asignarCliente($cliente);

    ItemPedido::create([
        'pedido_id' => $pedido->id,
        'producto_id' => $producto->id,
        'area_preparacion_id' => AreaPreparacion::factory()->for($empresa)->for($sede)->create()->id,
        'nombre_producto' => $producto->nombre,
        'precio_unitario' => $producto->precio,
        'cantidad' => 1,
    ]);

    Pago::create([
        'empresa_id' => $empresa->id, 'pedido_id' => $pedido->id, 'usuario_id' => $admin->id,
        'medio' => MedioPago::Efectivo, 'monto' => '10.00', 'idempotency_key' => 'idem-factus-cliente-juridico-pago',
    ]);

    $factura = FacturaElectronica::create([
        'empresa_id' => $empresa->id, 'pedido_id' => $pedido->id, 'proveedor' => 'factus',
        'estado' => EstadoFactura::Pendiente,
    ]);

    $proveedor = new FactusProveedor(baseUrl: 'https://api-sandbox.factus.com.co', clientId: 'id', clientSecret: 'secret', email: 'e', password: 'p');

    $proveedor->emitir($factura);

    Http::assertSent(fn ($request) => $request->url() === 'https://api-sandbox.factus.com.co/v2/bills/validate'
        && $request['customer']['company'] === 'Empresa Cliente SAS'
        && $request['customer']['dv'] === '7'
        && $request['customer']['legal_organization_code'] === '1'
        && ! array_key_exists('names', $request['customer']));
});

it('un mesero no puede buscar ni crear clientes, pero un cajero sí', function () {
    [$empresa, $sede] = crearEmpresaConAdminYSedeParaClientes('Empresa Permiso Buscar Cliente');

    $mesero = User::factory()->create();
    $mesero->accesos()->create(['empresa_id' => $empresa->id, 'sede_id' => $sede->id, 'rol' => Rol::Mesero]);

    $cajero = User::factory()->create();
    $cajero->accesos()->create(['empresa_id' => $empresa->id, 'sede_id' => $sede->id, 'rol' => Rol::Caja]);

    $this->actingAs($mesero);
    $this->getJson("/api/pos/sedes/{$sede->id}/clientes")->assertForbidden();
    $this->postJson("/api/pos/sedes/{$sede->id}/clientes", [
        'tipo_persona' => 'natural', 'tipo_documento' => '13', 'numero_documento' => '555', 'nombres' => 'Intento', 'apellidos' => 'Mesero',
    ])->assertForbidden();

    $this->actingAs($cajero);
    $this->postJson("/api/pos/sedes/{$sede->id}/clientes", [
        'tipo_persona' => 'natural', 'tipo_documento' => '13', 'numero_documento' => '555', 'nombres' => 'Cliente', 'apellidos' => 'Del Cajero',
    ])->assertOk()->assertJsonPath('data.nombre', 'Cliente Del Cajero');

    $this->getJson("/api/pos/sedes/{$sede->id}/clientes?buscar=Cliente Del")
        ->assertOk()
        ->assertJsonPath('data.0.nombre', 'Cliente Del Cajero');
});

/**
 * Bug real encontrado el 2026-09-08 al separar `nombre` en `nombres`/
 * `apellidos` (DEC-051): la búsqueda comparaba el término contra cada
 * columna por separado — un término que abarca AMBOS campos a la vez (ej.
 * "Juan Pérez", con nombres="Juan" y apellidos="Pérez") no coincidía con
 * ninguna columna individual y el buscador devolvía cero resultados.
 * Corregido comparando contra la concatenación `nombres || ' ' ||
 * apellidos` (portable entre PostgreSQL y SQLite), no solo las columnas
 * sueltas — ver ClienteController::index() y ClientesTable.php.
 */
it('la búsqueda de clientes encuentra por el nombre completo, no solo por una columna a la vez', function () {
    [$empresa, $sede] = crearEmpresaConAdminYSedeParaClientes('Empresa Busqueda Nombre Completo');

    $cajero = User::factory()->create();
    $cajero->accesos()->create(['empresa_id' => $empresa->id, 'sede_id' => $sede->id, 'rol' => Rol::Caja]);

    Cliente::create([
        'empresa_id' => $empresa->id, 'tipo_persona' => 'natural', 'tipo_documento' => '13',
        'numero_documento' => '777', 'nombres' => 'Juan Carlos', 'apellidos' => 'Pérez Gómez', 'estado' => 'activo',
    ]);

    $this->actingAs($cajero);

    // El término abarca el final de "nombres" y el inicio de "apellidos" —
    // exactamente el caso que rompía con columnas separadas sin concatenar.
    $this->getJson("/api/pos/sedes/{$sede->id}/clientes?buscar=Carlos Pérez")
        ->assertOk()
        ->assertJsonPath('data.0.nombre', 'Juan Carlos Pérez Gómez');
});

it('flujo completo: el cajero crea un cliente, lo asigna al pedido y la factura se emite a su nombre', function () {
    Queue::fake();

    [$empresa, $sede] = crearEmpresaConAdminYSedeParaClientes('Empresa Flujo Cliente Pos');
    $categoria = Categoria::factory()->for($empresa)->create();
    $producto = Producto::factory()->for($empresa)->for($categoria)->create(['precio' => '10.00']);
    $area = AreaPreparacion::factory()->for($empresa)->for($sede)->create();

    $cajero = User::factory()->create();
    $cajero->accesos()->create(['empresa_id' => $empresa->id, 'sede_id' => $sede->id, 'rol' => Rol::Caja]);

    $this->actingAs($cajero);

    $pedido = $this->postJson("/api/pos/sedes/{$sede->id}/pedidos", [
        'tipo' => 'mostrador', 'idempotency_key' => 'flujo-cliente-pedido-1',
    ])->json('data');

    $this->postJson("/api/pos/pedidos/{$pedido['id']}/items", [
        'producto_id' => $producto->id, 'area_preparacion_id' => $area->id, 'cantidad' => 1,
    ]);

    $cliente = $this->postJson("/api/pos/sedes/{$sede->id}/clientes", [
        'tipo_persona' => 'natural', 'tipo_documento' => '13', 'numero_documento' => '666', 'nombres' => 'Cliente', 'apellidos' => 'Flujo Completo',
    ])->assertOk()->json('data');

    $this->patchJson("/api/pos/pedidos/{$pedido['id']}/cliente", ['cliente_id' => $cliente['id']])
        ->assertOk()
        ->assertJsonPath('data.cliente.nombre', 'Cliente Flujo Completo');

    $this->postJson("/api/pos/pedidos/{$pedido['id']}/pagos", [
        'medio' => 'efectivo', 'monto' => '10.00', 'idempotency_key' => 'flujo-cliente-pago-1',
    ])->assertOk();

    expect(Pedido::find($pedido['id'])->cliente_id)->toBe($cliente['id']);
});
