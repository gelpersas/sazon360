<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            // Nullable: la mayoría de ventas de mostrador siguen siendo
            // anónimas ("consumidor final", ver config/facturacion.php) —
            // solo se asigna cuando el cajero elige facturar a un cliente
            // identificado (opcional, al cobrar).
            $table->foreignId('cliente_id')->nullable()->after('mesa_id')->constrained('clientes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cliente_id');
        });
    }
};
