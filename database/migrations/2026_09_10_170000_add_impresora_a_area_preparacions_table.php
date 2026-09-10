<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Impresión térmica por red, por área (ver docs/DECISIONES.md) — ambos
 * campos nullable: un área sin impresora configurada simplemente no
 * imprime, sigue funcionando solo con el KDS como hasta ahora.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('area_preparacions', function (Blueprint $table) {
            $table->string('impresora_ip')->nullable()->after('orden');
            $table->unsignedInteger('impresora_puerto')->nullable()->after('impresora_ip');
        });
    }

    public function down(): void
    {
        Schema::table('area_preparacions', function (Blueprint $table) {
            $table->dropColumn(['impresora_ip', 'impresora_puerto']);
        });
    }
};
