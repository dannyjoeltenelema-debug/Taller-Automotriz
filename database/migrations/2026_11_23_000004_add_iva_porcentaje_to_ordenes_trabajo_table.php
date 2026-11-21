<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * IVA histórico: cada OT guarda el % de IVA con el que se calculó. Al editar la OT y en su PDF
 * se usa este valor, nunca el vigente de empresa. Las OT existentes se rellenan con el % vigente
 * hoy en `empresa` (o el respaldo de config/taller.php si aún no hay fila).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordenes_trabajo', function (Blueprint $table) {
            $table->decimal('iva_porcentaje', 5, 2)->nullable()->after('iva');
        });

        $vigente = DB::table('empresa')->value('iva_porcentaje') ?? config('taller.iva_por_defecto');
        DB::table('ordenes_trabajo')->whereNull('iva_porcentaje')->update(['iva_porcentaje' => $vigente]);
    }

    public function down(): void
    {
        Schema::table('ordenes_trabajo', function (Blueprint $table) {
            $table->dropColumn('iva_porcentaje');
        });
    }
};
