<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** El taller (una sola fila). */
class Empresa extends Model
{
    protected $table = 'empresa';

    protected $fillable = ['razon_social', 'ruc', 'logo', 'iva_porcentaje', 'telefono', 'email', 'direccion'];

    protected $casts = ['iva_porcentaje' => 'decimal:2'];

    public function sedes(): HasMany
    {
        return $this->hasMany(Sede::class);
    }

    /** Datos del taller (cacheado por request). */
    public static function actual(): ?self
    {
        return once(fn () => static::query()->first());
    }

    public static function ivaPorcentaje(): float
    {
        return (float) (static::actual()?->iva_porcentaje ?? 15);
    }

    /** Teléfono en formato internacional para wa.me (593XXXXXXXXX). */
    public function telefonoWhatsapp(): string
    {
        $t = preg_replace('/\D/', '', (string) $this->telefono);
        if (str_starts_with($t, '0')) {
            $t = '593'.substr($t, 1);
        }

        return $t;
    }
}
