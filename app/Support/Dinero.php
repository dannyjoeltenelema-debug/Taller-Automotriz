<?php

namespace App\Support;

/**
 * Montos en USD con aritmética EXACTA (nunca float):
 *  - BCMath si la extensión está cargada (lo normal en Laragon y producción);
 *  - si no, enteros escalados (centavos / diezmilésimas) con PHP_INT.
 * Redondeo a 2 decimales "half up" (0.005 → 0.01; -0.005 → -0.01), igual que antes con number_format.
 * Cada monto se redondea ANTES de sumarse, para que los totales cuadren al centavo.
 */
class Dinero
{
    /** Redondea a 2 decimales y devuelve texto ("12.30"). */
    public static function r(float|int|string|null $valor): string
    {
        return self::redondear(self::normalizar($valor), 2);
    }

    public static function mostrar(float|int|string|null $valor): string
    {
        $r = self::r($valor);
        [$ent, $dec] = explode('.', ltrim($r, '-'));

        return '$ '.($r[0] === '-' ? '-' : '').number_format((int) $ent, 0, '.', ',').'.'.$dec;
    }

    /** Suma de montos, cada uno redondeado a 2 decimales antes de sumar. */
    public static function sumar(float|int|string|null ...$valores): string
    {
        $total = '0.00';
        foreach ($valores as $v) {
            $total = self::bc() ? bcadd($total, self::r($v), 2) : self::deCentavos(self::aCentavos($total) + self::aCentavos(self::r($v)));
        }

        return $total;
    }

    public static function restar(float|int|string|null $a, float|int|string|null $b): string
    {
        return self::bc() ? bcsub(self::r($a), self::r($b), 2) : self::deCentavos(self::aCentavos(self::r($a)) - self::aCentavos(self::r($b)));
    }

    /** round(round(a, 2) × round(b, 2), 2): cantidad × precio de una línea. */
    public static function multiplicar(float|int|string|null $a, float|int|string|null $b): string
    {
        if (self::bc()) {
            return self::redondear(bcmul(self::r($a), self::r($b), 4), 2);
        }

        // centavos × centavos = diezmilésimas exactas
        return self::deCentavos(self::dividirRedondeando(self::aCentavos(self::r($a)) * self::aCentavos(self::r($b)), 100));
    }

    /** round(base × porcentaje / 100, 2). */
    public static function porcentaje(float|int|string|null $base, float|int|string|null $porcentaje): string
    {
        if (self::bc()) {
            return self::redondear(bcdiv(bcmul(self::r($base), self::r($porcentaje), 4), '100', 6), 2);
        }

        // centavos × (porcentaje × 100) = base × porcentaje × 10⁴; /100 para el % y /10⁴ → centavos.
        return self::deCentavos(self::dividirRedondeando(self::aCentavos(self::r($base)) * self::aCentavos(self::r($porcentaje)), 10000));
    }

    // ───────────── Interno ─────────────

    private static ?bool $bcmath = null;

    /** Solo para tests: fuerza (true/false) o vuelve a detectar (null) el uso de BCMath. */
    public static function usarBcmath(?bool $usar): void
    {
        self::$bcmath = $usar;
    }

    private static function bc(): bool
    {
        return self::$bcmath ??= extension_loaded('bcmath');
    }

    /** Texto decimal sin exponente ni float. */
    private static function normalizar(float|int|string|null $valor): string
    {
        if ($valor === null || $valor === '') {
            return '0';
        }
        if (is_int($valor)) {
            return (string) $valor;
        }
        if (is_float($valor)) {
            // Representación decimal más corta que vuelve al mismo float (no arrastra 0.30000000000000004 a menos que exista).
            $valor = rtrim(rtrim(sprintf('%.12F', round($valor, 12)), '0'), '.');
        }
        $valor = trim((string) $valor);
        if (! is_numeric($valor)) {
            return '0';
        }
        if (stripos($valor, 'e') !== false) {
            $valor = rtrim(rtrim(sprintf('%.12F', (float) $valor), '0'), '.');
        }

        return $valor;
    }

    private static function redondear(string $valor, int $decimales): string
    {
        if (self::bc()) {
            $ajuste = '0.'.str_repeat('0', $decimales).'5';

            return str_starts_with($valor, '-')
                ? self::sinMenosCero(bcsub($valor, $ajuste, $decimales))
                : bcadd($valor, $ajuste, $decimales);
        }

        // Respaldo: escala a enteros de 10^(decimales+1) y redondea half up.
        $negativo = str_starts_with($valor, '-');
        [$ent, $dec] = array_pad(explode('.', ltrim($valor, '-+')), 2, '');
        $dec = str_pad($dec, $decimales + 1, '0');
        $escalado = (int) ($ent.substr($dec, 0, $decimales + 1));
        $r = intdiv($escalado + 5, 10);
        $txt = str_pad((string) $r, $decimales + 1, '0', STR_PAD_LEFT);
        $res = substr($txt, 0, -$decimales).'.'.substr($txt, -$decimales);

        return $negativo && $r !== 0 ? '-'.$res : $res;
    }

    private static function sinMenosCero(string $v): string
    {
        return preg_match('/^-0\.0+$/', $v) ? ltrim($v, '-') : $v;
    }

    private static function aCentavos(string $dosDecimales): int
    {
        $neg = str_starts_with($dosDecimales, '-');
        [$e, $d] = array_pad(explode('.', ltrim($dosDecimales, '-')), 2, '00');
        $c = (int) $e * 100 + (int) str_pad(substr($d, 0, 2), 2, '0');

        return $neg ? -$c : $c;
    }

    private static function deCentavos(int $c): string
    {
        $neg = $c < 0;
        $c = abs($c);

        return ($neg ? '-' : '').intdiv($c, 100).'.'.str_pad((string) ($c % 100), 2, '0', STR_PAD_LEFT);
    }

    /** División entera con redondeo half up (lejos de cero). */
    private static function dividirRedondeando(int $n, int $d): int
    {
        $q = intdiv(abs($n) * 2 + $d, 2 * $d);

        return $n < 0 ? -$q : $q;
    }
}
