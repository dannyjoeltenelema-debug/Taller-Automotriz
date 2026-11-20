<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->enum('tipo_documento', ['cedula', 'ruc', 'pasaporte']);
            $table->string('numero_documento', 20)->unique();
            $table->string('nombres', 150)->index();
            $table->string('telefono', 20);
            $table->string('email', 120)->nullable();
            $table->string('direccion')->nullable();
            $table->foreignId('empresa_colaboradora_id')->nullable()->constrained('empresas_colaboradoras')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
