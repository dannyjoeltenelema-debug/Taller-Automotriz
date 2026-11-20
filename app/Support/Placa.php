<?php

namespace App\Support;

/**
 * Placas vehiculares de Ecuador.
 * Se guardan normalizadas (MAYÚSCULAS, sin espacios ni guiones: PBA4521)
 * y se muestran formateadas (PBA-4521).
 */
class Placa
{
    public static function normalizar(?string $placa): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $placa));
    }

    public static function formatear(?string $placa): string
    {
        $p = self::normalizar($placa);

        if (preg_match('/^([A-Z]{2,3})(\d{3,4}[A-Z]?)$/', $p, $m)) {
            return $m[1].'-'.$m[2];
        }

        return $p;
    }

    /** ¿Parece una placa? (autos: ABC1234 / ABC123, motos: AB123C) */
    public static function pareceValida(?string $placa): bool
    {
        return (bool) preg_match('/^[A-Z]{2,3}\d{3,4}[A-Z]?$/', self::normalizar($placa));
    }
}
