<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `porcentaje` permite repartir un mismo ítem entre varias sub-cuentas (ej.
 * un postre compartido a la mitad) — validado en aplicación (no en BD) que
 * la suma de porcentajes de un mismo item_pedido_id entre todas sus
 * sub-cuentas no supere 100 — ver App\Models\SubCuenta::crear().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sub_cuenta_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sub_cuenta_id')->constrained('sub_cuentas')->cascadeOnDelete();
            $table->foreignId('item_pedido_id')->constrained('item_pedidos')->restrictOnDelete();
            $table->decimal('porcentaje', 5, 2)->default(100.00);
            $table->timestamps();

            $table->unique(['sub_cuenta_id', 'item_pedido_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sub_cuenta_items');
    }
};
