<?php

namespace App\Providers;

use App\Contracts\ProveedorFacturacionElectronica;
use App\Services\Facturacion\FactusProveedor;
use App\Services\Facturacion\NuloProveedor;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Selección del proveedor de facturación electrónica por config, no
        // hardcodeada — ver docs/DECISIONES.md DEC-019.
        $this->app->bind(ProveedorFacturacionElectronica::class, function () {
            return match (config('facturacion.proveedor')) {
                'factus' => new FactusProveedor(
                    baseUrl: config('facturacion.factus.base_url'),
                    clientId: config('facturacion.factus.client_id'),
                    clientSecret: config('facturacion.factus.client_secret'),
                    email: config('facturacion.factus.email'),
                    password: config('facturacion.factus.password'),
                    numberingRangeId: config('facturacion.factus.numbering_range_id'),
                    clienteGenerico: config('facturacion.factus.cliente_generico'),
                    paymentMethodCodes: config('facturacion.factus.payment_method_code'),
                ),
                default => new NuloProveedor,
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
