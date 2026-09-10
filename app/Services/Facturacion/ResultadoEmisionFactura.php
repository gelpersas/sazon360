<?php

namespace App\Services\Facturacion;

/**
 * Resultado neutral (independiente del proveedor) de intentar emitir una
 * factura electrónica — ver App\Contracts\ProveedorFacturacionElectronica.
 */
final readonly class ResultadoEmisionFactura
{
    public function __construct(
        public bool $exitoso,
        public ?string $numero = null,
        public ?string $cufe = null,
        public ?string $pdfUrl = null,
        public ?string $xmlUrl = null,
        public ?string $mensajeError = null,
        public array $respuestaCruda = [],
    ) {}

    public static function exitoso(
        ?string $numero = null,
        ?string $cufe = null,
        ?string $pdfUrl = null,
        ?string $xmlUrl = null,
        array $respuestaCruda = [],
    ): self {
        return new self(
            exitoso: true,
            numero: $numero,
            cufe: $cufe,
            pdfUrl: $pdfUrl,
            xmlUrl: $xmlUrl,
            respuestaCruda: $respuestaCruda,
        );
    }

    public static function fallido(string $mensajeError, array $respuestaCruda = []): self
    {
        return new self(
            exitoso: false,
            mensajeError: $mensajeError,
            respuestaCruda: $respuestaCruda,
        );
    }
}
