<?php

namespace App\Services\Facturacion;

use App\Contracts\ProveedorFacturacionElectronica;
use App\Models\FacturaElectronica;

/**
 * Proveedor por defecto en desarrollo local (config('facturacion.proveedor')
 * = 'nulo', el default si no se configura ninguno). No llama a ningún
 * servicio externo ni tiene validez fiscal real ante la DIAN — simula una
 * emisión exitosa para poder probar el flujo completo (pedido cobrado →
 * factura → visible en Filament) sin depender de credenciales de un
 * proveedor real. Nunca debe usarse en producción — ver docs/DECISIONES.md
 * DEC-019.
 */
class NuloProveedor implements ProveedorFacturacionElectronica
{
    public function emitir(FacturaElectronica $factura): ResultadoEmisionFactura
    {
        return ResultadoEmisionFactura::exitoso(
            numero: "SIMULADO-{$factura->pedido_id}",
            cufe: 'SIMULADO-SIN-VALIDEZ-FISCAL',
            respuestaCruda: [
                'nota' => 'Proveedor nulo: emisión simulada, sin validez fiscal ante la DIAN. Solo para desarrollo/demo local.',
            ],
        );
    }
}
