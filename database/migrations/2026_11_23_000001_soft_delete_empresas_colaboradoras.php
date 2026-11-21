<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Empresas colaboradoras: "activo" se reemplaza por soft delete (deleted_at).
 *
 * Conserva el comportamiento actual: un convenio inactivo ya daba 0 % de descuento, así que
 * sus filas pasan a eliminadas y sus clientes a "No aplica" (empresa_colaboradora_id = null).
 * Las OT ya emitidas no se tocan (guardan sus montos).
 *
 * down(): recrea "activo" (eliminadas → activo = false) y quita deleted_at. Lo que NO puede
 * revertir es el vínculo de los clientes que se pasaron a "No aplica" (no se guardó el anterior);
 * de todos modos esos clientes no tenían descuento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas_colaboradoras', function (Blueprint $table) {
            $table->softDeletes();
        });

        DB::transaction(function () {
            $inactivas = DB::table('empresas_colaboradoras')->where('activo', false)->pluck('id');
            if ($inactivas->isNotEmpty()) {
                DB::table('clientes')->whereIn('empresa_colaboradora_id', $inactivas)->update(['empresa_colaboradora_id' => null]);
                DB::table('empresas_colaboradoras')->whereIn('id', $inactivas)->update(['deleted_at' => now()]);
            }
        });

        Schema::table('empresas_colaboradoras', function (Blueprint $table) {
            $table->dropColumn('activo');
        });
    }

    public function down(): void
    {
        Schema::table('empresas_colaboradoras', function (Blueprint $table) {
            $table->boolean('activo')->default(true)->after('telefono');
        });

        DB::table('empresas_colaboradoras')->whereNotNull('deleted_at')->update(['activo' => false]);

        Schema::table('empresas_colaboradoras', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
