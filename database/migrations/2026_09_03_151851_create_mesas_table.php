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
        Schema::create('mesas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->restrictOnDelete();
            $table->foreignId('sede_id')->constrained('sedes')->restrictOnDelete();
            $table->string('nombre');
            $table->string('piso')->nullable();
            $table->string('zona')->nullable();
            $table->unsignedInteger('capacidad')->nullable();
            $table->string('estado')->default('activa');
            $table->timestamps();

            $table->unique(['sede_id', 'nombre']);
            $table->index(['empresa_id', 'sede_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mesas');
    }
};
