<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('factura_electronicas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('pedido_id')->constrained('pedidos')->cascadeOnDelete();
            $table->string('proveedor');
            $table->string('estado')->default('pendiente');
            $table->string('numero')->nullable();
            $table->string('cufe')->nullable();
            $table->string('pdf_url')->nullable();
            $table->string('xml_url')->nullable();
            $table->text('error_mensaje')->nullable();
            $table->json('respuesta_proveedor')->nullable();
            $table->timestamps();

            // Un pedido genera a lo sumo una factura electrónica — reintentar
            // una emisión fallida actualiza esta misma fila, no crea otra.
            $table->unique('pedido_id');
            $table->index('empresa_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('factura_electronicas');
    }
};
