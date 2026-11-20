<?php

namespace App\Support;

/**
 * Montos en USD a 2 decimales. Cada monto se redondea con number_format
 * ANTES de sumarse, para que los totales cuadren al centavo.
 */
class Dinero
{
    public static function r(float|int|string|null $valor): string
    {
        return number_format((float) $valor, 2, '.', '');
    }

    public static function mostrar(float|int|string|null $valor): string
    {
        return '$ '.number_format((float) $valor, 2, '.', ',');
    }
}
