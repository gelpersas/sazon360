<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cajas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->restrictOnDelete();
            $table->foreignId('sede_id')->constrained('sedes')->restrictOnDelete();
            $table->foreignId('usuario_apertura_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('usuario_cierre_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->decimal('monto_inicial', 10, 2);
            $table->decimal('monto_cierre_esperado', 10, 2)->nullable();
            $table->decimal('monto_cierre_real', 10, 2)->nullable();
            $table->decimal('diferencia', 10, 2)->nullable();
            $table->string('estado')->default('abierta');
            $table->text('nota_apertura')->nullable();
            $table->text('nota_cierre')->nullable();
            $table->timestamp('abierta_at');
            $table->timestamp('cerrada_at')->nullable();
            $table->timestamps();

            $table->index(['sede_id', 'estado']);
        });

        // Como mucho una caja abierta por sede a la vez (ver docs/DECISIONES.md
        // DEC-008). Índice único parcial: lockForUpdate() en la aplicación no
        // evita la condición de carrera en el INSERT cuando no hay ninguna
        // fila previa que bloquear; esta restricción sí, a nivel de base de
        // datos. Sintaxis válida tanto en PostgreSQL como en SQLite (tests).
        DB::statement(
            'CREATE UNIQUE INDEX cajas_una_abierta_por_sede ON cajas (sede_id) WHERE estado = \'abierta\''
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cajas');
    }
};
