<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reemplaza el único campo `nombre` por campos separados según el tipo de
 * persona — pedido explícito del usuario (2026-09-08): una persona natural
 * necesita nombres/apellidos por separado, una persona jurídica necesita
 * razón social, y ambas pueden tener un nombre comercial (campo real y
 * distinto en el esquema de Factus: `trade_name`, separado de `company`/
 * `names` — confirmado contra el SDK oficial, ver docs/DECISIONES.md
 * DEC-046/DEC-050). No hay backfill: se confirmó que `clientes` no tiene
 * ningún registro real todavía (el módulo se creó el 2026-09-07).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('nombres')->nullable()->after('tipo_persona');
            $table->string('apellidos')->nullable()->after('nombres');
            $table->string('razon_social')->nullable()->after('apellidos');
            $table->string('nombre_comercial')->nullable()->after('razon_social');
            $table->dropColumn('nombre');
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('nombre')->after('tipo_persona');
            $table->dropColumn(['nombres', 'apellidos', 'razon_social', 'nombre_comercial']);
        });
    }
};
