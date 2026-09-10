<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla nativa de Filament v4 (Actions\Exports) — necesaria para el botón
 * "Exportar" (Excel/CSV) de VentaResource (ver docs/DECISIONES.md DEC-067).
 * Copiada de vendor/filament/actions/database/migrations/create_exports_table.php
 * en vez de publicarla, para mantener el mismo patrón de timestamp de
 * archivo que el resto de migraciones del proyecto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exports', function (Blueprint $table): void {
            $table->id();
            $table->timestamp('completed_at')->nullable();
            $table->string('file_disk');
            $table->string('file_name')->nullable();
            $table->string('exporter');
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('total_rows');
            $table->unsignedInteger('successful_rows')->default(0);
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exports');
    }
};
