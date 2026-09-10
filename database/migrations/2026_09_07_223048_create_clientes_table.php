<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->restrictOnDelete();
            $table->string('tipo_persona');
            // Código DIAN de tipo de documento (mismo criterio que
            // `productos.codigo_impuesto_dian`: código crudo, no un enum de
            // PHP — ver App\Enums\TipoPersona).
            $table->string('tipo_documento');
            $table->string('numero_documento');
            // Dígito de verificación (solo aplica si tipo_documento es NIT) —
            // capturado a mano, no calculado (mismo criterio que Empresa.dv,
            // ver DEC-035: no inventar/calcular datos fiscales que Sazón360
            // no puede verificar).
            $table->string('dv', 1)->nullable();
            $table->string('nombre');
            $table->string('direccion')->nullable();
            $table->string('telefono')->nullable();
            $table->string('email')->nullable();
            $table->string('estado')->default('activo');
            $table->timestamps();

            $table->unique(['empresa_id', 'tipo_documento', 'numero_documento']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
