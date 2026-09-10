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
        Schema::table('empresas', function (Blueprint $table) {
            // Nombre comercial para mostrar en recibos — 'nombre' sigue
            // siendo el identificador interno (slug, tenancy de Filament).
            $table->string('nombre_comercial')->nullable()->after('nombre');

            // NIT + DV: inmutables una vez fijados (ver Empresa::booted()),
            // el DV se captura a mano, no se calcula (decisión del usuario).
            $table->string('nit', 20)->nullable()->after('nombre_comercial');
            $table->string('dv', 1)->nullable()->after('nit');

            $table->string('regimen_tributario')->nullable()->after('dv');
            $table->string('actividad_economica_ciiu')->nullable()->after('regimen_tributario');

            $table->string('direccion')->nullable()->after('actividad_economica_ciiu');
            $table->string('telefono')->nullable()->after('direccion');
            $table->string('whatsapp')->nullable()->after('telefono');
            $table->string('email')->nullable()->after('whatsapp');

            $table->string('logo_path')->nullable()->after('email');
            $table->text('notas_internas')->nullable()->after('logo_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn([
                'nombre_comercial',
                'nit',
                'dv',
                'regimen_tributario',
                'actividad_economica_ciiu',
                'direccion',
                'telefono',
                'whatsapp',
                'email',
                'logo_path',
                'notas_internas',
            ]);
        });
    }
};
