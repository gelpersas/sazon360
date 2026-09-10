<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Igual que `pedido_id` (Fase 5): un movimiento generado por una compra o un
 * traslado queda enlazado a su origen para trazabilidad, no solo al texto
 * libre de `motivo` — ver docs/DECISIONES.md (Fase 7).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimiento_inventarios', function (Blueprint $table) {
            $table->foreignId('compra_id')->nullable()->after('pedido_id')->constrained('compras')->restrictOnDelete();
            $table->foreignId('traslado_inventario_id')->nullable()->after('compra_id')->constrained('traslado_inventarios')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('movimiento_inventarios', function (Blueprint $table) {
            $table->dropConstrainedForeignId('compra_id');
            $table->dropConstrainedForeignId('traslado_inventario_id');
        });
    }
};
