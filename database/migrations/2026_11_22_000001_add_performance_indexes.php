<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índices compuestos para las consultas más frecuentes (auditoría de rendimiento).
 * Los índices simples (placa, numero_documento, codigo, qr_token, estado, fecha_ingreso)
 * ya existían; estos cubren filtro + orden en un solo recorrido.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordenes_trabajo', function (Blueprint $table) {
            // Listado de OT: WHERE estado IN (…) ORDER BY fecha_ingreso DESC
            $table->index(['estado', 'fecha_ingreso'], 'ot_estado_fecha_idx');
            // Ficha del vehículo / portal: WHERE vehiculo_id = ? ORDER BY fecha_ingreso DESC
            $table->index(['vehiculo_id', 'fecha_ingreso'], 'ot_vehiculo_fecha_idx');
        });

        Schema::table('citas', function (Blueprint $table) {
            // Listado de citas: WHERE estado IN (…) ORDER BY fecha_deseada, hora_inicio
            $table->index(['estado', 'fecha_deseada', 'hora_inicio'], 'citas_estado_fecha_hora_idx');
        });
    }

    public function down(): void
    {
        Schema::table('ordenes_trabajo', function (Blueprint $table) {
            $table->dropIndex('ot_estado_fecha_idx');
            $table->dropIndex('ot_vehiculo_fecha_idx');
        });

        Schema::table('citas', function (Blueprint $table) {
            $table->dropIndex('citas_estado_fecha_hora_idx');
        });
    }
};
