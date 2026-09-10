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
        Schema::table('grupo_mesas', function (Blueprint $table) {
            // 'independiente' (default, todo el comportamiento previo a esta
            // migración) o 'general' (un solo pedido compartido por todo el
            // grupo, contra la mesa principal — ver docs/DECISIONES.md).
            $table->string('modo')->default('independiente')->after('estado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('grupo_mesas', function (Blueprint $table) {
            $table->dropColumn('modo');
        });
    }
};
