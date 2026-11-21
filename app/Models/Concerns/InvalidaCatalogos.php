<?php

namespace App\Models\Concerns;

use App\Support\Catalogos;

/**
 * Cualquier alta/edición/baja del modelo invalida la caché de catálogos.
 * Ojo: los updates masivos por query builder (Modelo::where()->update()) no disparan
 * eventos; para esos llamar Catalogos::olvidar() a mano (o esperar el TTL de respaldo).
 */
trait InvalidaCatalogos
{
    protected static function bootInvalidaCatalogos(): void
    {
        static::saved(fn () => Catalogos::olvidar());
        static::deleted(fn () => Catalogos::olvidar());
    }
}
