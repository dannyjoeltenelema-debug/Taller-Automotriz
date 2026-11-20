<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimientos_inventario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos');
            $table->foreignId('sede_id')->constrained('sedes');
            $table->enum('tipo', ['entrada', 'salida', 'ajuste']);
            $table->decimal('cantidad', 12, 2);
            $table->decimal('costo_unitario', 12, 2)->nullable();
            $table->decimal('stock_resultante', 12, 2)->nullable();
            $table->foreignId('proveedor_id')->nullable()->constrained('proveedores')->nullOnDelete(); // entradas
            $table->foreignId('orden_id')->nullable()->constrained('ordenes_trabajo')->nullOnDelete(); // salidas
            $table->foreignId('empleado_id')->nullable()->constrained('empleados')->nullOnDelete();
            $table->string('nota')->nullable();
            $table->timestamps();
            $table->index(['producto_id', 'sede_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_inventario');
    }
};
