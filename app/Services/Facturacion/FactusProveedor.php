<?php

namespace App\Services\Facturacion;

use App\Contracts\ProveedorFacturacionElectronica;
use App\Enums\EstadoComanda;
use App\Enums\TipoPersona;
use App\Models\Cliente;
use App\Models\FacturaElectronica;
use App\Models\ItemPedido;
use App\Models\Pago;
use App\Models\Pedido;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Adaptador para Factus (https://developers.factus.com.co/), proveedor
 * colombiano de facturación electrónica DIAN — primera implementación
 * concreta del contrato ProveedorFacturacionElectronica (ver
 * docs/DECISIONES.md DEC-019/DEC-020/DEC-022).
 *
 * VERIFICADO contra el sandbox real de Factus (V2, credenciales de prueba
 * del usuario): autenticación OAuth2 y `POST /v2/bills/validate` emiten
 * correctamente una factura validada (`is_validated: true`, CUFE y número
 * oficial reales) — ver DEC-022 para el detalle completo de la prueba.
 *
 * Alcance deliberadamente reducido, no adivinado, en un punto donde la
 * documentación no alcanza a cubrir un dato que Sazón360 no captura hoy:
 * pedidos donde el total pagado no coincide exactamente con el total del
 * pedido (ej. pago en efectivo con cambio) — el signo que Factus espera en
 * `cash_rounding_amount` para reconciliar esa diferencia no está confirmado,
 * así que esos pedidos se rechazan explícitamente en vez de arriesgar un
 * valor mal firmado ante la DIAN.
 *
 * Sin configurar el impuesto DIAN de un producto (`codigo_impuesto_dian`/
 * `tasa_iva` en `Producto`), su pedido se rechaza en vez de inventar un
 * código de impuesto — es una decisión fiscal real de Dulcita/su contador.
 */
class FactusProveedor implements ProveedorFacturacionElectronica
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $clientId,
        private readonly ?string $clientSecret,
        private readonly ?string $email,
        private readonly ?string $password,
        private readonly ?string $numberingRangeId = null,
        private readonly array $clienteGenerico = [],
        private readonly array $paymentMethodCodes = [],
    ) {}

    public function emitir(FacturaElectronica $factura): ResultadoEmisionFactura
    {
        if (! $this->clientId || ! $this->clientSecret || ! $this->email || ! $this->password) {
            return ResultadoEmisionFactura::fallido(
                'Proveedor Factus sin configurar: faltan credenciales (FACTUS_CLIENT_ID/FACTUS_CLIENT_SECRET/FACTUS_EMAIL/FACTUS_PASSWORD en .env). '
                .'Ver docs/DECISIONES.md DEC-019.',
            );
        }

        $pedido = $factura->pedido()
            ->with([
                'items' => fn ($query) => $query
                    ->whereDoesntHave('comanda', fn ($comanda) => $comanda->where('estado', EstadoComanda::Anulada))
                    ->with('producto'),
                'pagos',
                'cliente',
            ])
            ->firstOrFail();

        $productosSinDatosFiscales = $pedido->items
            ->filter(fn (ItemPedido $item) => ! $item->producto->tieneDatosFiscalesCompletos())
            ->pluck('producto.nombre')
            ->unique();

        if ($productosSinDatosFiscales->isNotEmpty()) {
            return ResultadoEmisionFactura::fallido(
                'No se puede facturar: falta configurar el impuesto DIAN de: '.$productosSinDatosFiscales->implode(', ')
                .' (Filament → Productos → editar → Impuesto DIAN / Tasa).',
            );
        }

        $total = $pedido->total();
        $totalPagado = bcadd((string) $pedido->pagos->sum('monto'), '0', 2);

        if (bccomp($total, $totalPagado, 2) !== 0) {
            return ResultadoEmisionFactura::fallido(
                "No se puede facturar automáticamente: el total pagado (\${$totalPagado}) no coincide exactamente con el total del pedido (\${$total}). ".
                'El signo que Factus espera en cash_rounding_amount para reconciliar esta diferencia (ej. cambio en efectivo) no está confirmado — '
                .'ver docs/DECISIONES.md DEC-019.',
            );
        }

        try {
            $token = $this->autenticar();
        } catch (Throwable $e) {
            return ResultadoEmisionFactura::fallido("No se pudo autenticar contra Factus: {$e->getMessage()}");
        }

        try {
            $respuesta = Http::withToken($token)
                ->asJson()
                ->acceptJson()
                ->post("{$this->baseUrl}/v2/bills/validate", $this->construirPayload($pedido));
        } catch (Throwable $e) {
            return ResultadoEmisionFactura::fallido("No se pudo conectar con Factus: {$e->getMessage()}");
        }

        if ($respuesta->failed()) {
            return ResultadoEmisionFactura::fallido(
                'Factus rechazó la factura (HTTP '.$respuesta->status().'): '.($respuesta->json('message') ?? $respuesta->body()),
                $respuesta->json() ?? [],
            );
        }

        $datos = $respuesta->json('data', []);

        return ResultadoEmisionFactura::exitoso(
            numero: $datos['number'] ?? null,
            cufe: $datos['cufe'] ?? null,
            // Confirmado contra el sandbox real: no hay un link de PDF
            // directo en esta respuesta, pero `links.public_url` es la vista
            // pública del documento — es lo más útil que se puede mostrar en
            // Filament sin llamar a un endpoint de descarga aparte.
            pdfUrl: $datos['links']['public_url'] ?? null,
            respuestaCruda: $respuesta->json() ?? [],
        );
    }

    private function construirPayload(Pedido $pedido): array
    {
        $payload = [
            // Estable entre reintentos (misma factura, distintos intentos):
            // evita que Factus registre dos facturas oficiales por una sola
            // venta si se reintenta tras un fallo transitorio.
            'reference_code' => "SAZON360-PEDIDO-{$pedido->id}",
            'payment_details' => $this->construirPagos($pedido->pagos),
            'customer' => $this->construirCliente($pedido->cliente),
            'items' => $this->construirItems($pedido->items),
        ];

        if ($this->numberingRangeId) {
            $payload['numbering_range_id'] = (int) $this->numberingRangeId;
        }

        return $payload;
    }

    /**
     * Si el pedido no tiene un `Cliente` asignado (venta anónima de
     * mostrador, el caso normal), factura al "consumidor final" genérico
     * de `config('facturacion.factus.cliente_generico')` — mismo
     * comportamiento que antes de que existiera `Cliente` (ver
     * docs/DECISIONES.md). Los nombres de campo del payload están
     * confirmados contra el SDK oficial de Factus (sbetav/factus-js,
     * `CustomerInput`), no adivinados: `names` es para persona natural
     * (nombres+apellidos), `company` para persona jurídica (razón social)
     * — nunca ambos a la vez. `trade_name` (nombre comercial) es un campo
     * real y separado del esquema, válido para ambos tipos — ver DEC-050.
     */
    private function construirCliente(?Cliente $cliente): array
    {
        if (! $cliente) {
            return $this->clienteGenerico;
        }

        $payload = [
            'identification_document_code' => $cliente->tipo_documento->value,
            'identification' => $cliente->numero_documento,
            'legal_organization_code' => $cliente->tipo_persona->codigoFactus(),
        ];

        if ($cliente->tipo_persona === TipoPersona::Juridica) {
            $payload['company'] = $cliente->razon_social;
        } else {
            $payload['names'] = trim("{$cliente->nombres} {$cliente->apellidos}");
        }

        $camposOpcionales = [
            'dv' => 'dv',
            'nombre_comercial' => 'trade_name',
            'direccion' => 'address',
            'telefono' => 'phone',
            'email' => 'email',
        ];

        foreach ($camposOpcionales as $campoCliente => $campoFactus) {
            if (filled($cliente->{$campoCliente})) {
                $payload[$campoFactus] = $cliente->{$campoCliente};
            }
        }

        return $payload;
    }

    /**
     * @param  Collection<int, Pago>  $pagos
     */
    private function construirPagos(Collection $pagos): array
    {
        return $pagos->map(fn (Pago $pago) => [
            'payment_form' => '1', // Contado — Sazón360 no modela ventas a crédito (fiado) todavía.
            'payment_method_code' => $this->paymentMethodCodes[$pago->medio->value] ?? '10',
            'reference_code' => "PAGO-{$pago->id}",
            'amount' => (string) $pago->monto,
        ])->all();
    }

    /**
     * @param  Collection<int, ItemPedido>  $items
     */
    private function construirItems(Collection $items): array
    {
        return $items->map(fn (ItemPedido $item) => [
            'code_reference' => "PROD-{$item->producto_id}",
            'name' => $item->nombre_producto,
            'quantity' => sprintf('%.2f', $item->cantidad),
            'discount_rate' => '0.00',
            'price' => $this->precioSinImpuesto((string) $item->precio_unitario, (string) $item->producto->tasa_iva),
            'unit_measure_code' => '94', // Unidad — Dulcita vende por unidad, no por peso/volumen.
            'standard_code' => '999', // "Adopción del contribuyente" — default genérico documentado por Factus.
            'taxes' => [
                [
                    'code' => $item->producto->codigo_impuesto_dian,
                    'rate' => sprintf('%.2f', $item->producto->tasa_iva),
                ],
            ],
        ])->all();
    }

    /**
     * `Producto.precio` en Sazón360 es el precio final que paga el cliente
     * (IVA incluido — convención habitual en Colombia, es literalmente lo
     * que `Pago::registrar()` cobra, sin sumar impuesto aparte). Factus, en
     * cambio, espera en `items[].price` el precio SIN impuestos y calcula el
     * total sumando el impuesto encima — confirmado contra el sandbox real
     * (un pedido de $10.000 con IVA 19% fue rechazado porque Factus esperaba
     * un total de $11.900). Se calcula la base gravable hacia atrás para que
     * el total que Factus reconstruye coincida con lo que realmente se cobró.
     */
    private function precioSinImpuesto(string $precioConImpuesto, string $tasaIva): string
    {
        $factor = bcadd('1', bcdiv($tasaIva, '100', 10), 10);
        $base = bcdiv($precioConImpuesto, $factor, 10);

        return sprintf('%.2f', (float) $base);
    }

    private function autenticar(): string
    {
        $respuesta = Http::asJson()
            ->post("{$this->baseUrl}/oauth/token", [
                'grant_type' => 'password',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'username' => $this->email,
                'password' => $this->password,
            ])
            ->throw();

        $token = $respuesta->json('access_token');

        if (! $token) {
            throw new RuntimeException('Factus respondió sin access_token.');
        }

        return $token;
    }
}
