<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auditoría del panel (tabla propia, sin paquetes). Solo se inserta, nunca se actualiza:
 * por eso no tiene updated_at. Nunca guarda contraseñas, remember_token ni secretos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auditorias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('accion', 30);                       // creado | actualizado | eliminado | restaurado | login | logout | login_fallido | …
            $table->string('auditable_type', 120)->nullable();  // clase del modelo (null en login fallido)
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->json('valores_antes')->nullable();
            $table->json('valores_despues')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->foreignId('sede_id')->nullable()->constrained('sedes')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['user_id', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditorias');
    }
};
