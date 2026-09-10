<?php

namespace App\Contracts;

use App\Models\FacturaElectronica;
use App\Services\Facturacion\ResultadoEmisionFactura;

/**
 * Contrato del que depende el módulo de facturación (App\Models\FacturaElectronica,
 * App\Jobs\EmitirFacturaElectronicaJob) — ver docs/DECISIONES.md DEC-019. El
 * usuario pidió explícitamente poder cambiar de proveedor tecnológico de
 * facturación electrónica sin tocar el resto del sistema: cualquier proveedor
 * nuevo se agrega implementando esta interfaz y seleccionándola en
 * config('facturacion.proveedor'), sin modificar Pedido/Pago/el Job ni los
 * Resources de Filament.
 */
interface ProveedorFacturacionElectronica
{
    public function emitir(FacturaElectronica $factura): ResultadoEmisionFactura;
}
