<?php

namespace App\Support;

/** Enmascarado de datos sensibles para el portal público. */
class Mascara
{
    /** 1712345678 → 17******78 */
    public static function documento(?string $valor): string
    {
        $v = (string) $valor;
        $n = strlen($v);

        if ($n <= 4) {
            return str_repeat('*', $n);
        }

        return substr($v, 0, 2).str_repeat('*', $n - 4).substr($v, -2);
    }

    /** 9BGKS48U0EG123456 → ************23456 (últimos 5) */
    public static function vin(?string $valor): string
    {
        $v = (string) $valor;
        if ($v === '') {
            return '—';
        }

        return str_repeat('*', max(strlen($v) - 5, 0)).substr($v, -5);
    }

    /** "Juan Carlos Pérez Mora" → "Juan C. P." */
    public static function nombre(?string $valor): string
    {
        $partes = preg_split('/\s+/', trim((string) $valor)) ?: [];
        if (! $partes) {
            return '';
        }
        $primero = array_shift($partes);

        return trim($primero.' '.implode(' ', array_map(fn ($p) => mb_substr($p, 0, 1).'.', $partes)));
    }
}
