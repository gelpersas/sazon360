<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grupo_mesas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->restrictOnDelete();
            $table->foreignId('sede_id')->constrained('sedes')->restrictOnDelete();
            $table->foreignId('mesa_principal_id')->constrained('mesas')->restrictOnDelete();
            $table->string('estado')->default('activo');
            $table->foreignId('creado_por_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('disuelto_por_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('disuelto_en')->nullable();
            $table->timestamps();

            $table->index(['sede_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grupo_mesas');
    }
};
