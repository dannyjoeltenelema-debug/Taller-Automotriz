<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ordenes_trabajo', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique(); // OT-2026-0001
            $table->foreignId('sede_id')->constrained('sedes');
            $table->foreignId('vehiculo_id')->constrained('vehiculos');
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->foreignId('asesor_id')->constrained('empleados');
            $table->foreignId('tecnico_id')->nullable()->constrained('empleados')->nullOnDelete();
            $table->dateTime('fecha_ingreso')->index();
            $table->dateTime('fecha_entrega')->nullable();
            $table->unsignedInteger('kilometraje_ingreso');
            $table->unsignedInteger('proximo_kilometraje')->nullable();
            $table->string('nivel_combustible', 10)->nullable();
            $table->text('observaciones_recepcion')->nullable();
            $table->json('checklist')->nullable();
            $table->text('diagnostico')->nullable();
            $table->enum('estado', ['recibido', 'en_proceso', 'listo_entrega', 'entregado', 'cancelado'])->default('recibido')->index();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('descuento', 12, 2)->default(0);
            $table->decimal('iva', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->enum('estado_cobro', ['pendiente', 'pagado'])->default('pendiente');
            $table->enum('forma_cobro', ['efectivo', 'transferencia', 'tarjeta'])->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ordenes_trabajo');
    }
};
