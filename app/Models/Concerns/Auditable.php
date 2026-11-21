<?php

namespace App\Models\Concerns;

use App\Models\Auditoria;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Auditoría automática de un modelo: creado, actualizado (solo los campos que cambiaron),
 * eliminado (incluye soft delete) y restaurado. Cada evento = 1 INSERT en `auditorias`,
 * sin SELECT extra (usa los atributos que el modelo ya tiene en memoria).
 *
 * Uso:   use Auditable;
 * Opcional en el modelo:
 *   protected array $auditarExcluir = ['campo_ruidoso'];   // no se registran
 *
 * Nunca se guardan password, remember_token ni secretos (ver Auditoria::CAMPOS_SECRETOS:
 * si cambian, se registra "[oculto]"). Los updates masivos por query builder
 * (Modelo::where()->update()) no disparan eventos y no quedan auditados.
 *
 * Listo para Inventario: el otro dev solo tiene que agregar `use Auditable;` a sus modelos
 * y su nombre a Auditoria::MODULOS.
 */
trait Auditable
{
    protected static function bootAuditable(): void
    {
        static::created(function ($m) {
            Auditoria::registrar('creado', $m, null, $m->valoresAuditables($m->getAttributes()));
        });

        static::updated(function ($m) {
            $despues = $m->valoresAuditables($m->getChanges());
            // Restaurar un soft delete también dispara "updated" (solo deleted_at): lo cubre "restaurado".
            unset($despues['deleted_at']);
            if ($despues === []) {
                return;
            }
            $antes = array_intersect_key($m->getRawOriginal(), $despues);
            Auditoria::registrar('actualizado', $m, $antes, $despues);
        });

        static::deleted(function ($m) {
            Auditoria::registrar('eliminado', $m, $m->valoresAuditables($m->getRawOriginal()), null);
        });

        if (in_array(SoftDeletes::class, class_uses_recursive(static::class), true)) {
            static::restored(function ($m) {
                Auditoria::registrar('restaurado', $m, null, ['deleted_at' => null]);
            });
        }
    }

    /** Quita campos ignorados/excluidos. Los secretos se reemplazan en Auditoria::limpiar(). */
    public function valoresAuditables(array $valores): array
    {
        $fuera = array_merge(Auditoria::CAMPOS_IGNORADOS, $this->auditarExcluir ?? []);

        return array_diff_key($valores, array_flip($fuera));
    }
}
