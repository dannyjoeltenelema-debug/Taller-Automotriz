<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empleados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sede_id')->constrained('sedes');
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->string('nombres', 100);
            $table->string('apellidos', 100);
            $table->string('cedula', 10)->unique();
            $table->string('telefono', 20)->nullable();
            $table->enum('cargo', ['admin', 'asesor', 'tecnico', 'mecanico']);
            $table->string('especialidad', 100)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->index(['cargo', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empleados');
    }
};
