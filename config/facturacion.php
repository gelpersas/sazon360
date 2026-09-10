<?php

return [
    /*
     * Proveedor de facturación electrónica activo. 'nulo' (default) simula
     * una emisión exitosa sin llamar a ningún servicio externo — sin validez
     * fiscal real, solo para desarrollo/demo local. 'factus' usa el
     * adaptador de Factus (ver App\Services\Facturacion\FactusProveedor).
     * Cambiar de proveedor no requiere tocar código, solo esta variable y
     * sus credenciales — ver docs/DECISIONES.md DEC-019.
     */
    'proveedor' => env('FACTURACION_PROVEEDOR', 'nulo'),

    'factus' => [
        'base_url' => env('FACTUS_BASE_URL', 'https://api-sandbox.factus.com.co'),
        'client_id' => env('FACTUS_CLIENT_ID'),
        'client_secret' => env('FACTUS_CLIENT_SECRET'),
        'email' => env('FACTUS_EMAIL'),
        'password' => env('FACTUS_PASSWORD'),

        /*
         * ID del rango de numeración de Factus (GET /v2/numbering-ranges) a
         * usar para emitir. Solo es obligatorio si Dulcita tiene más de un
         * rango activo en Factus — si solo tiene uno, se puede dejar null y
         * Factus lo infiere. Sin verificar contra una cuenta real todavía.
         */
        'numbering_range_id' => env('FACTUS_NUMBERING_RANGE_ID'),

        /*
         * El POS no captura datos del cliente final (venta de mostrador
         * anónima) — se factura al "consumidor final" genérico, la
         * convención estándar de la DIAN para este caso. Si Dulcita
         * necesita facturar a un cliente identificado (ej. venta a
         * empresas), este es un cambio de alcance a futuro, no cubierto
         * todavía.
         */
        'cliente_generico' => [
            'identification_document_code' => env('FACTUS_CLIENTE_GENERICO_TIPO_DOC', '13'),
            'identification' => env('FACTUS_CLIENTE_GENERICO_IDENTIFICACION', '222222222222'),
            'legal_organization_code' => env('FACTUS_CLIENTE_GENERICO_ORG', '2'),
            'names' => env('FACTUS_CLIENTE_GENERICO_NOMBRE', 'Consumidor final'),
        ],

        /*
         * Mapeo de App\Enums\MedioPago a los códigos DIAN de
         * payment_method_code que espera Factus. "10" (efectivo) y "42"
         * (consignación/transferencia) vienen confirmados de la
         * documentación de Factus provista por el usuario; "48" (tarjeta
         * crédito) es la convención DIAN estándar pero NO vino confirmada en
         * esa documentación — revisar contra el catálogo completo de Factus
         * antes de facturar una venta con tarjeta en producción.
         */
        'payment_method_code' => [
            'efectivo' => '10',
            'tarjeta' => '48',
            'transferencia' => '42',
        ],
    ],
];
