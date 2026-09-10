<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cierra a nivel de base de datos el hueco que la migración original de
     * `accesos` dejó anotado como pendiente ("duplicados se evitan a nivel
     * de aplicación, no de constraint, para MVP"): un mismo usuario podía
     * quedar dos veces con el mismo rol en la misma sede (o dos veces como
     * administración central de la misma empresa) sin que nada en el
     * esquema lo impidiera — solo la validación del formulario de
     * AccesoResource. Verificado antes de aplicar: 0 filas duplicadas en la
     * base de datos de desarrollo.
     *
     * Dos restricciones, porque Postgres/SQLite no tratan NULL como igual a
     * NULL en un UNIQUE normal (por eso la migración original no puso una
     * sola constraint de una vez — ver su propio comentario):
     * 1. UNIQUE normal para accesos con sede (mesero/caja/área/admin_sede).
     * 2. Índice único parcial (mismo patrón que `cajas_una_abierta_por_sede`,
     *    válido en Postgres y SQLite) para administración central
     *    (sede_id IS NULL), donde el UNIQUE normal no alcanza.
     */
    public function up(): void
    {
        Schema::table('accesos', function (Blueprint $table) {
            $table->unique(['user_id', 'empresa_id', 'sede_id', 'rol'], 'accesos_usuario_sede_rol_unico');
        });

        DB::statement(
            'CREATE UNIQUE INDEX accesos_admin_central_unico ON accesos (user_id, empresa_id, rol) WHERE sede_id IS NULL'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS accesos_admin_central_unico');

        Schema::table('accesos', function (Blueprint $table) {
            $table->dropUnique('accesos_usuario_sede_rol_unico');
        });
    }
};
