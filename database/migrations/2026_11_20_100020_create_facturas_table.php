<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facturas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orden_id')->nullable()->constrained('ordenes_trabajo')->nullOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->foreignId('sede_id')->constrained('sedes');
            $table->date('fecha_emision');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('descuento', 12, 2)->default(0);
            $table->decimal('iva', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->enum('estado', ['borrador', 'emitida', 'anulada'])->default('borrador');
            // Campos SRI (facturación electrónica): se completan en un sprint posterior.
            $table->string('establecimiento', 3)->nullable();
            $table->string('punto_emision', 3)->nullable();
            $table->string('secuencial', 9)->nullable();
            $table->string('clave_acceso', 49)->nullable()->unique();
            $table->string('numero_autorizacion', 49)->nullable();
            $table->dateTime('fecha_autorizacion')->nullable();
            $table->string('ambiente_sri', 1)->nullable();
            $table->string('estado_sri', 20)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facturas');
    }
};
