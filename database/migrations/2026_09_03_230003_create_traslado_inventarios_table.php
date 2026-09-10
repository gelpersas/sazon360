<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('traslado_inventarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->restrictOnDelete();
            $table->foreignId('insumo_id')->constrained('insumos')->restrictOnDelete();
            $table->foreignId('sede_origen_id')->constrained('sedes')->restrictOnDelete();
            $table->foreignId('sede_destino_id')->constrained('sedes')->restrictOnDelete();
            $table->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
            $table->decimal('cantidad', 10, 3);
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->index('sede_origen_id');
            $table->index('sede_destino_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('traslado_inventarios');
    }
};
