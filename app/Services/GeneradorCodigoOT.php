<?php

namespace App\Services;

use App\Models\Empresa;
use App\Models\OrdenTrabajo;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Código secuencial de OT por año: OT-2026-0001.
 *
 * Debe llamarse DENTRO de una transacción. Se toma un bloqueo exclusivo
 * (SELECT ... FOR UPDATE) sobre la fila única de `empresa` como mutex y luego se
 * lee con lockForUpdate el último código del año. Otra transacción concurrente
 * espera hasta el commit de esta, así que nunca obtiene el mismo número.
 * El índice UNIQUE de `codigo` es la última barrera.
 */
class GeneradorCodigoOT
{
    public function siguiente(?int $anio = null): string
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('GeneradorCodigoOT::siguiente() debe ejecutarse dentro de DB::transaction().');
        }

        $anio ??= (int) now()->format('Y');
        $prefijo = "OT-{$anio}-";

        // 1) Mutex: bloqueo de la fila única de `empresa`. Las transacciones concurrentes
        //    esperan aquí en orden (sin los deadlocks que producen los gap locks del rango).
        Empresa::query()->lockForUpdate()->first(['id']);

        // 2) Lectura bloqueante (lee lo último confirmado, no el snapshot) del último código del año.
        $ultimo = OrdenTrabajo::query()
            ->where('codigo', 'like', $prefijo.'%')
            ->orderByDesc('codigo')
            ->lockForUpdate()
            ->value('codigo');

        $n = $ultimo ? ((int) substr($ultimo, strlen($prefijo))) + 1 : 1;

        return $prefijo.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }
}
