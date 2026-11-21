<?php

namespace App\Services;

use App\Models\Empresa;
use App\Models\OrdenTrabajo;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;

/**
 * Código secuencial de OT por año: OT-2026-0001 (prefijo y relleno en config/taller.php → codigo_ot).
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
        $prefijo = self::prefijo($anio);

        // 1) Mutex: bloqueo de la fila única de `empresa`. Las transacciones concurrentes
        //    esperan aquí en orden (sin los deadlocks que producen los gap locks del rango).
        //    Sin esa fila NO hay mutex: se corta con un error claro en vez de seguir sin bloqueo.
        if (! Empresa::query()->lockForUpdate()->first(['id'])) {
            throw new RuntimeException('No existe la fila de empresa (Datos del taller): se necesita para numerar las OT sin duplicados. Ejecute los seeders o registre los datos del taller.');
        }

        // 2) Lectura bloqueante (lee lo último confirmado, no el snapshot) del último código del año.
        $ultimo = OrdenTrabajo::query()
            ->where('codigo', 'like', $prefijo.'%')
            ->orderByDesc('codigo')
            ->lockForUpdate()
            ->value('codigo');

        $n = $ultimo ? ((int) substr($ultimo, strlen($prefijo))) + 1 : 1;

        return $prefijo.str_pad((string) $n, (int) config('taller.codigo_ot.relleno'), '0', STR_PAD_LEFT);
    }

    /** "OT-2026-" (sin número). */
    public static function prefijo(?int $anio = null): string
    {
        return config('taller.codigo_ot.prefijo').'-'.($anio ?? (int) now()->format('Y')).'-';
    }
}
