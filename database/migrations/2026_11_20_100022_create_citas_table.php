<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('citas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sede_id')->constrained('sedes');
            $table->foreignId('vehiculo_id')->nullable()->constrained('vehiculos')->nullOnDelete();
            $table->foreignId('orden_id')->nullable()->constrained('ordenes_trabajo')->nullOnDelete();
            $table->string('placa', 10)->index();
            $table->string('cliente_nombre', 150);
            $table->string('cliente_telefono', 20);
            $table->date('fecha_deseada');
            $table->time('hora_inicio'); // franja de 1 hora
            $table->string('motivo', 150);
            $table->text('observacion')->nullable();
            $table->enum('origen', ['qr_vehiculo', 'recepcion']);
            $table->enum('estado', ['pendiente', 'confirmada', 'atendida', 'cancelada'])->default('pendiente')->index();
            $table->timestamps();
            $table->index(['fecha_deseada', 'hora_inicio']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('citas');
    }
};
