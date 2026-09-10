<?php

use App\Enums\EstadoFactura;
use App\Enums\MedioPago;
use App\Enums\Rol;
use App\Enums\TipoPedido;
use App\Filament\Resources\FacturaElectronicas\Pages\ListFacturasElectronicas;
use App\Jobs\EmitirFacturaElectronicaJob;
use App\Models\AreaPreparacion;
use App\Models\Empresa;
use App\Models\FacturaElectronica;
use App\Models\ItemPedido;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Sede;
use App\Models\User;
use App\Policies\FacturaElectronicaPolicy;
use App\Services\Facturacion\FactusProveedor;
use App\Services\Facturacion\NuloProveedor;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

function crearEmpresaConAdminYSedeParaFacturacion(string $nombreEmpresa): array
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

function crearPedidoCobrable(Empresa $empresa, Sede $sede, User $usuario, string $idempotencyKey): Pedido
{
    $producto = Producto::factory()->create(['empresa_id' => $empresa->id, 'precio' => '5.00']);
    $pedido = Pedido::abrir($sede, $usuario, TipoPedido::Mostrador, null, $idempotencyKey);

    ItemPedido::create([
        'pedido_id' => $pedido->id,
        'producto_id' => $producto->id,
        'area_preparacion_id' => AreaPreparacion::factory()->for($empresa)->for($sede)->create()->id,
        'nombre_producto' => $producto->nombre,
        'precio_unitario' => $producto->precio,
        'cantidad' => 1,
    ]);

    return $pedido;
}

it('cobrar un pedido crea su factura electrónica pendiente y encola la emisión', function () {
    Queue::fake();

    [$empresa, $sede, $admin] = crearEmpresaConAdminYSedeParaFacturacion('Empresa Factura Pendiente');
    $pedido = crearPedidoCobrable($empresa, $sede, $admin, 'idem-pedido-factura-1');

    Pago::registrar($pedido, $admin, MedioPago::Efectivo, $pedido->total(), 'idem-pago-factura-1');

    $factura = FacturaElectronica::where('pedido_id', $pedido->id)->first();

    expect($factura)->not->toBeNull();
    expect($factura->estado)->toBe(EstadoFactura::Pendiente);
    expect($factura->proveedor)->toBe(config('facturacion.proveedor'));

    Queue::assertPushed(EmitirFacturaElectronicaJob::class, fn (EmitirFacturaElectronicaJob $job) => $job->factura->is($factura));
});

it('un pedido solo genera una factura electrónica aunque se procese su pago más de una vez', function () {
    [$empresa, $sede, $admin] = crearEmpresaConAdminYSedeParaFacturacion('Empresa Factura Unica');
    $pedido = crearPedidoCobrable($empresa, $sede, $admin, 'idem-pedido-factura-unica');

    Pago::registrar($pedido, $admin, MedioPago::Efectivo, $pedido->total(), 'idem-pago-factura-unica');
    // Reintento con la misma idempotency_key: Pago::registrar() devuelve el
    // pago ya existente sin volver a ejecutar el bloque de cobro.
    Pago::registrar($pedido, $admin, MedioPago::Efectivo, $pedido->total(), 'idem-pago-factura-unica');

    expect(FacturaElectronica::where('pedido_id', $pedido->id)->count())->toBe(1);
});

it('el Job de emisión, con el proveedor nulo, marca la factura como emitida con un CUFE simulado', function () {
    [$empresa, $sede, $admin] = crearEmpresaConAdminYSedeParaFacturacion('Empresa Job Nulo');
    $pedido = crearPedidoCobrable($empresa, $sede, $admin, 'idem-pedido-job-nulo');

    $factura = FacturaElectronica::create([
        'empresa_id' => $empresa->id,
        'pedido_id' => $pedido->id,
        'proveedor' => 'nulo',
        'estado' => EstadoFactura::Pendiente,
    ]);

    (new EmitirFacturaElectronicaJob($factura))->handle(new NuloProveedor);

    $factura->refresh();
    expect($factura->estado)->toBe(EstadoFactura::Emitida);
    expect($factura->cufe)->toBe('SIMULADO-SIN-VALIDEZ-FISCAL');
    expect($factura->numero)->toBe("SIMULADO-{$pedido->id}");
});

it('el Job de emisión marca la factura como rechazada si el proveedor lanza una excepción', function () {
    [$empresa, $sede, $admin] = crearEmpresaConAdminYSedeParaFacturacion('Empresa Job Fallo');
    $pedido = crearPedidoCobrable($empresa, $sede, $admin, 'idem-pedido-job-fallo');

    $factura = FacturaElectronica::create([
        'empresa_id' => $empresa->id,
        'pedido_id' => $pedido->id,
        'proveedor' => 'factus',
        'estado' => EstadoFactura::Pendiente,
    ]);

    // FactusProveedor sin credenciales: emitir() devuelve un resultado
    // fallido (no lanza), así que el Job debe marcar rechazada igual.
    $proveedorSinCredenciales = new FactusProveedor(baseUrl: 'https://api-sandbox.factus.com.co', clientId: null, clientSecret: null, email: null, password: null);

    (new EmitirFacturaElectronicaJob($factura))->handle($proveedorSinCredenciales);

    $factura->refresh();
    expect($factura->estado)->toBe(EstadoFactura::Rechazada);
    expect($factura->error_mensaje)->toContain('sin configurar');
});

it('reintentar() vuelve a encolar una factura rechazada sin crear una fila nueva', function () {
    Queue::fake();

    [$empresa, $sede, $admin] = crearEmpresaConAdminYSedeParaFacturacion('Empresa Reintentar');
    $pedido = crearPedidoCobrable($empresa, $sede, $admin, 'idem-pedido-reintentar');

    $factura = FacturaElectronica::create([
        'empresa_id' => $empresa->id,
        'pedido_id' => $pedido->id,
        'proveedor' => 'nulo',
        'estado' => EstadoFactura::Rechazada,
        'error_mensaje' => 'fallo previo',
    ]);

    $factura->reintentar();

    expect(FacturaElectronica::where('pedido_id', $pedido->id)->count())->toBe(1);
    expect($factura->fresh()->estado)->toBe(EstadoFactura::Pendiente);
    expect($factura->fresh()->error_mensaje)->toBeNull();

    Queue::assertPushed(EmitirFacturaElectronicaJob::class);
});

it('solo administración (central o de sede) puede reintentar una factura rechazada', function () {
    [$empresa, $sede, $adminCentral] = crearEmpresaConAdminYSedeParaFacturacion('Empresa Permiso Reintentar');

    $cajero = User::factory()->create();
    $cajero->accesos()->create(['empresa_id' => $empresa->id, 'sede_id' => $sede->id, 'rol' => Rol::Caja]);

    $pedido = crearPedidoCobrable($empresa, $sede, $adminCentral, 'idem-pedido-permiso');
    $factura = FacturaElectronica::create([
        'empresa_id' => $empresa->id,
        'pedido_id' => $pedido->id,
        'proveedor' => 'nulo',
        'estado' => EstadoFactura::Rechazada,
    ]);

    Filament::setTenant($empresa, isQuiet: true);
    $policy = new FacturaElectronicaPolicy;

    expect($policy->update($adminCentral, $factura))->toBeTrue();
    expect($policy->update($cajero, $factura))->toBeFalse();
});

it('FactusProveedor rechaza la emisión si algún producto no tiene impuesto DIAN configurado', function () {
    [$empresa, $sede, $admin] = crearEmpresaConAdminYSedeParaFacturacion('Empresa Factus Sin Impuesto');
    $pedido = crearPedidoCobrable($empresa, $sede, $admin, 'idem-factus-sin-impuesto');

    $factura = FacturaElectronica::create([
        'empresa_id' => $empresa->id,
        'pedido_id' => $pedido->id,
        'proveedor' => 'factus',
        'estado' => EstadoFactura::Pendiente,
    ]);

    $proveedor = new FactusProveedor(baseUrl: 'https://api-sandbox.factus.com.co', clientId: 'id', clientSecret: 'secret', email: 'e@e.com', password: 'pass');

    $resultado = $proveedor->emitir($factura);

    expect($resultado->exitoso)->toBeFalse();
    expect($resultado->mensajeError)->toContain('Impuesto DIAN');
});

it('FactusProveedor rechaza la emisión si el total pagado no coincide exactamente con el total del pedido', function () {
    [$empresa, $sede, $admin] = crearEmpresaConAdminYSedeParaFacturacion('Empresa Factus Desfase');
    $producto = Producto::factory()->create(['empresa_id' => $empresa->id, 'precio' => '10.00', 'codigo_impuesto_dian' => '01', 'tasa_iva' => '19.00']);
    $pedido = Pedido::abrir($sede, $admin, TipoPedido::Mostrador, null, 'idem-factus-desfase');

    ItemPedido::create([
        'pedido_id' => $pedido->id,
        'producto_id' => $producto->id,
        'area_preparacion_id' => AreaPreparacion::factory()->for($empresa)->for($sede)->create()->id,
        'nombre_producto' => $producto->nombre,
        'precio_unitario' => $producto->precio,
        'cantidad' => 1,
    ]);

    // Pago::create() directo (no Pago::registrar()) para simular un
    // sobrepago (ej. cambio en efectivo) sin disparar el flujo normal de
    // cobro — el total pagado (15.00) no coincide con el total (10.00).
    Pago::create([
        'empresa_id' => $empresa->id,
        'pedido_id' => $pedido->id,
        'usuario_id' => $admin->id,
        'medio' => MedioPago::Efectivo,
        'monto' => '15.00',
        'idempotency_key' => 'idem-factus-desfase-pago',
    ]);

    $factura = FacturaElectronica::create([
        'empresa_id' => $empresa->id,
        'pedido_id' => $pedido->id,
        'proveedor' => 'factus',
        'estado' => EstadoFactura::Pendiente,
    ]);

    $proveedor = new FactusProveedor(baseUrl: 'https://api-sandbox.factus.com.co', clientId: 'id', clientSecret: 'secret', email: 'e@e.com', password: 'pass');
    $resultado = $proveedor->emitir($factura);

    expect($resultado->exitoso)->toBeFalse();
    expect($resultado->mensajeError)->toContain('no coincide exactamente');
});

it('FactusProveedor emite correctamente cuando el producto tiene impuesto configurado y Factus responde con éxito', function () {
    Http::fake([
        '*/oauth/token' => Http::response(['access_token' => 'token-de-prueba'], 200),
        '*/v2/bills/validate' => Http::response([
            'status' => 'Created',
            'data' => ['number' => 'SETP990002443', 'cufe' => 'cufe-de-prueba', 'is_validated' => true],
        ], 201),
    ]);

    [$empresa, $sede, $admin] = crearEmpresaConAdminYSedeParaFacturacion('Empresa Factus Exito');
    $producto = Producto::factory()->create(['empresa_id' => $empresa->id, 'precio' => '10.00', 'codigo_impuesto_dian' => '01', 'tasa_iva' => '19.00']);
    $pedido = Pedido::abrir($sede, $admin, TipoPedido::Mostrador, null, 'idem-factus-exito');

    ItemPedido::create([
        'pedido_id' => $pedido->id,
        'producto_id' => $producto->id,
        'area_preparacion_id' => AreaPreparacion::factory()->for($empresa)->for($sede)->create()->id,
        'nombre_producto' => $producto->nombre,
        'precio_unitario' => $producto->precio,
        'cantidad' => 1,
    ]);

    Pago::create([
        'empresa_id' => $empresa->id,
        'pedido_id' => $pedido->id,
        'usuario_id' => $admin->id,
        'medio' => MedioPago::Efectivo,
        'monto' => '10.00',
        'idempotency_key' => 'idem-factus-exito-pago',
    ]);

    $factura = FacturaElectronica::create([
        'empresa_id' => $empresa->id,
        'pedido_id' => $pedido->id,
        'proveedor' => 'factus',
        'estado' => EstadoFactura::Pendiente,
    ]);

    $proveedor = new FactusProveedor(
        baseUrl: 'https://api-sandbox.factus.com.co',
        clientId: 'id',
        clientSecret: 'secret',
        email: 'e@e.com',
        password: 'pass',
        clienteGenerico: ['identification_document_code' => '13', 'identification' => '222222222222', 'legal_organization_code' => '2', 'names' => 'Consumidor final'],
        paymentMethodCodes: ['efectivo' => '10', 'tarjeta' => '48', 'transferencia' => '42'],
    );

    $resultado = $proveedor->emitir($factura);

    expect($resultado->exitoso)->toBeTrue();
    expect($resultado->numero)->toBe('SETP990002443');
    expect($resultado->cufe)->toBe('cufe-de-prueba');

    Http::assertSent(fn ($request) => $request->url() === 'https://api-sandbox.factus.com.co/v2/bills/validate'
        && $request['reference_code'] === "SAZON360-PEDIDO-{$pedido->id}"
        && $request['items'][0]['taxes'][0]['code'] === '01'
        && $request['items'][0]['taxes'][0]['rate'] === '19.00'
        // precio_unitario (10.00) es el precio final con IVA incluido —
        // Factus espera la base SIN impuesto en `price` y calcula el
        // impuesto encima (confirmado contra el sandbox real, ver DEC-022).
        && $request['items'][0]['price'] === '8.40'
        && $request['payment_details'][0]['amount'] === '10.00'
        && $request['payment_details'][0]['payment_method_code'] === '10'
        && $request['customer']['names'] === 'Consumidor final');
});

it('FactusProveedor calcula correctamente el precio base sin impuesto a partir del precio final (IVA incluido)', function () {
    $proveedor = new FactusProveedor(baseUrl: 'https://api-sandbox.factus.com.co', clientId: 'id', clientSecret: 'secret', email: 'e', password: 'p');

    $metodo = new ReflectionMethod($proveedor, 'precioSinImpuesto');
    $metodo->setAccessible(true);

    // Caso verificado contra el sandbox real de Factus: $10.000 con IVA 19%
    // → base $8.403,36 (Factus reconstruye el total exacto: 8403.36 + 19% = 10000.00).
    expect($metodo->invoke($proveedor, '10000.00', '19.00'))->toBe('8403.36');
    expect($metodo->invoke($proveedor, '10.00', '19.00'))->toBe('8.40');
    expect($metodo->invoke($proveedor, '100.00', '0.00'))->toBe('100.00');
});

it('el listado de facturación electrónica de Filament respeta el aislamiento por sede y por tenant', function () {
    [$empresaA, $sedeA, $adminA] = crearEmpresaConAdminYSedeParaFacturacion('Empresa Factura Aislamiento A');
    [$empresaB, $sedeB, $adminB] = crearEmpresaConAdminYSedeParaFacturacion('Empresa Factura Aislamiento B');

    $pedidoA = crearPedidoCobrable($empresaA, $sedeA, $adminA, 'idem-pedido-aislamiento-a');
    FacturaElectronica::create([
        'empresa_id' => $empresaA->id,
        'pedido_id' => $pedidoA->id,
        'proveedor' => 'nulo',
        'estado' => EstadoFactura::Emitida,
        'numero' => 'FACTURA-EXCLUSIVA-A',
    ]);

    $pedidoB = crearPedidoCobrable($empresaB, $sedeB, $adminB, 'idem-pedido-aislamiento-b');
    FacturaElectronica::create([
        'empresa_id' => $empresaB->id,
        'pedido_id' => $pedidoB->id,
        'proveedor' => 'nulo',
        'estado' => EstadoFactura::Emitida,
        'numero' => 'FACTURA-EXCLUSIVA-B',
    ]);

    $this->actingAs($adminA)
        ->get("/admin/{$empresaA->slug}/factura-electronicas")
        ->assertOk()
        ->assertSee('FACTURA-EXCLUSIVA-A')
        ->assertDontSee('FACTURA-EXCLUSIVA-B');

    $this->actingAs($adminA)
        ->get("/admin/{$empresaB->slug}/factura-electronicas")
        ->assertNotFound();
});

it('la acción "Ver factura" solo aparece cuando la factura ya tiene un pdf_url', function () {
    [$empresa, $sede, $admin] = crearEmpresaConAdminYSedeParaFacturacion('Empresa Ver Factura');

    $pedidoSinPdf = crearPedidoCobrable($empresa, $sede, $admin, 'idem-pedido-sin-pdf');
    $facturaSinPdf = FacturaElectronica::create([
        'empresa_id' => $empresa->id,
        'pedido_id' => $pedidoSinPdf->id,
        'proveedor' => 'nulo',
        'estado' => EstadoFactura::Rechazada,
    ]);

    $pedidoConPdf = crearPedidoCobrable($empresa, $sede, $admin, 'idem-pedido-con-pdf');
    $facturaConPdf = FacturaElectronica::create([
        'empresa_id' => $empresa->id,
        'pedido_id' => $pedidoConPdf->id,
        'proveedor' => 'nulo',
        'estado' => EstadoFactura::Emitida,
        'numero' => 'FACTURA-CON-PDF',
        'pdf_url' => 'https://sandbox.factus.com.co/documentos/factura-con-pdf',
    ]);

    $this->actingAs($admin);
    Filament::setTenant($empresa, isQuiet: true);

    Livewire::test(ListFacturasElectronicas::class)
        ->assertTableActionHidden('verFactura', $facturaSinPdf)
        ->assertTableActionVisible('verFactura', $facturaConPdf);
});
