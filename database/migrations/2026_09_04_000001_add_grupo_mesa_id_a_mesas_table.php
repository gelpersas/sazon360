<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nula mientras la mesa opera sola; se llena al unirse a un grupo
 * (GrupoMesa::unir()) y vuelve a null al disolverse — ver
 * docs/DECISIONES.md (unión/división de mesas).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mesas', function (Blueprint $table) {
            $table->foreignId('grupo_mesa_id')->nullable()->after('estado')->constrained('grupo_mesas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('mesas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('grupo_mesa_id');
        });
    }
};
