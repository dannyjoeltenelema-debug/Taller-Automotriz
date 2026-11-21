<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\EmpresaColaboradora;
use Illuminate\Support\Facades\DB;

/**
 * Eliminar / restaurar empresas colaboradoras (soft delete).
 *
 * - Eliminar: en una transacción, sus clientes pasan a "No aplica" (sin descuento) y luego
 *   se hace el soft delete. Las OT ya emitidas guardan sus montos: no se recalculan.
 * - Restaurar: solo devuelve el convenio. Los clientes NO se vuelven a vincular solos.
 */
class GestorConvenios
{
    /** @return int clientes que quedaron en "No aplica" */
    public function eliminar(EmpresaColaboradora $convenio): int
    {
        return DB::transaction(function () use ($convenio) {
            // Updates de una fila a la vez no hacen falta: un UPDATE masivo basta (Cliente no tiene eventos ni caché).
            $desvinculados = Cliente::where('empresa_colaboradora_id', $convenio->id)->update(['empresa_colaboradora_id' => null]);
            $convenio->delete();

            return $desvinculados;
        });
    }

    public function restaurar(EmpresaColaboradora $convenio): void
    {
        $convenio->restore();
    }
}
