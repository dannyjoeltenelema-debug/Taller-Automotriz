<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehiculos', function (Blueprint $table) {
            $table->id();
            $table->string('placa', 10)->unique(); // normalizada: PBA4521
            $table->uuid('qr_token')->unique();
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->foreignId('modelo_id')->constrained('modelos');
            $table->unsignedSmallInteger('anio');
            $table->string('color', 40);
            $table->string('vin', 17)->nullable();
            $table->string('numero_motor', 40)->nullable();
            $table->enum('tipo_combustible', ['gasolina', 'diesel', 'hibrido', 'electrico', 'glp'])->default('gasolina');
            $table->enum('transmision', ['manual', 'automatica'])->default('manual');
            $table->unsignedInteger('intervalo_mantenimiento_km')->default(5000);
            $table->unsignedInteger('kilometraje_actual')->nullable(); // cache del último km registrado
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehiculos');
    }
};
