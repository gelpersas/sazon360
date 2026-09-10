<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('accesos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('sede_id')->nullable()->constrained('sedes')->cascadeOnDelete();
            $table->string('rol');
            $table->timestamps();

            // Sin unique(user_id, empresa_id, sede_id, rol): Postgres no trata NULLs como
            // iguales, así que no evitaría duplicados de administracion_central (sede_id null).
            // Duplicados se evitan a nivel de aplicación (formulario), no de constraint, para MVP.
            $table->index(['empresa_id', 'sede_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accesos');
    }
};
