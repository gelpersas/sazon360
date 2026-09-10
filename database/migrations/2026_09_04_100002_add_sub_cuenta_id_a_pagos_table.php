<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nula para un pago normal (contra el pedido completo, como siempre); se
 * llena cuando el pago se registró para saldar una sub-cuenta concreta. No
 * cambia en nada cuándo el pedido queda `cobrado` (sigue siendo la suma de
 * TODOS los pagos, etiquetados o no, contra `Pedido.total()`) ni toca
 * `descontarInventario()`/`FacturaElectronica` — ver docs/DECISIONES.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->foreignId('sub_cuenta_id')->nullable()->after('pedido_id')->constrained('sub_cuentas')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sub_cuenta_id');
        });
    }
};
