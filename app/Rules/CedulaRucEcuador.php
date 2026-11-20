<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valida cédula (10 dígitos) o RUC (13 dígitos) ecuatoriano sin paquetes externos.
 *
 *  - Cédula: provincia 01–24 (o 30 = extranjeros), tercer dígito < 6,
 *    dígito verificador por módulo 10 (coeficientes 2,1,2,1,...).
 *  - RUC persona natural: cédula válida + "001".
 *  - RUC sociedad privada (3er díg. 9) / pública (3er díg. 6): se valida estructura
 *    (provincia, tercer dígito y sufijo 001 / 0001). No se exige el dígito
 *    verificador módulo 11 para no rechazar RUC con numeración nueva del SRI.
 *
 * $tipo: 'cedula' | 'ruc' | null (se deduce por longitud).
 */
class CedulaRucEcuador implements ValidationRule
{
    public function __construct(private ?string $tipo = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $v = (string) $value;
        $tipo = $this->tipo ?? (strlen($v) === 13 ? 'ruc' : 'cedula');

        $ok = match ($tipo) {
            'cedula' => self::cedulaValida($v),
            'ruc' => self::rucValido($v),
            default => true,
        };

        if (! $ok) {
            $fail($tipo === 'ruc'
                ? 'El RUC ingresado no es válido (13 dígitos terminados en 001).'
                : 'La cédula ingresada no es válida.');
        }
    }

    public static function cedulaValida(string $c): bool
    {
        if (! preg_match('/^\d{10}$/', $c)) {
            return false;
        }

        $provincia = (int) substr($c, 0, 2);
        if (! (($provincia >= 1 && $provincia <= 24) || $provincia === 30)) {
            return false;
        }

        if ((int) $c[2] >= 6) {
            return false;
        }

        $suma = 0;
        for ($i = 0; $i < 9; $i++) {
            $d = (int) $c[$i] * ($i % 2 === 0 ? 2 : 1);
            $suma += $d > 9 ? $d - 9 : $d;
        }
        $verificador = (10 - ($suma % 10)) % 10;

        return $verificador === (int) $c[9];
    }

    public static function rucValido(string $r): bool
    {
        if (! preg_match('/^\d{13}$/', $r) || ! str_ends_with($r, '001')) {
            return false;
        }

        $tercero = (int) $r[2];

        if ($tercero < 6) {
            return self::cedulaValida(substr($r, 0, 10));
        }

        $provincia = (int) substr($r, 0, 2);
        if (! (($provincia >= 1 && $provincia <= 24) || $provincia === 30)) {
            return false;
        }

        return match ($tercero) {
            9 => true,
            6 => str_ends_with($r, '0001'),
            default => false,
        };
    }
}
