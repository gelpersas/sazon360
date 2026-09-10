<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Datos fiscales necesarios para facturar electrónicamente ante la DIAN
 * (ver docs/DECISIONES.md DEC-019) — sin valor por defecto a propósito: qué
 * impuesto/tasa aplica a cada producto de Dulcita (IVA, INC, excluido, y a
 * qué tasa) es una decisión fiscal real que debe confirmar el usuario/su
 * contador, no algo que el código deba adivinar. Mientras un producto no
 * tenga esto configurado, FactusProveedor rechaza explícitamente emitir su
 * factura en vez de enviar un código de impuesto inventado a la DIAN.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->string('codigo_impuesto_dian')->nullable()->after('precio');
            $table->decimal('tasa_iva', 5, 2)->nullable()->after('codigo_impuesto_dian');
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn(['codigo_impuesto_dian', 'tasa_iva']);
        });
    }
};
