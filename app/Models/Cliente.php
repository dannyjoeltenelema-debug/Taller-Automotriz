<?php

namespace App\Models;

use App\Support\Placa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends Model
{
    public const TIPOS_DOCUMENTO = ['cedula' => 'Cédula', 'ruc' => 'RUC', 'pasaporte' => 'Pasaporte'];

    protected $fillable = [
        'tipo_documento', 'numero_documento', 'nombres', 'telefono', 'email', 'direccion', 'empresa_colaboradora_id',
    ];

    public function empresaColaboradora(): BelongsTo
    {
        return $this->belongsTo(EmpresaColaboradora::class);
    }

    public function vehiculos(): HasMany
    {
        return $this->hasMany(Vehiculo::class);
    }

    public function ordenes(): HasMany
    {
        return $this->hasMany(OrdenTrabajo::class);
    }

    /** % de descuento vigente por convenio (0 si "No aplica" o convenio inactivo). */
    public function porcentajeDescuento(): float
    {
        $convenio = $this->empresaColaboradora;

        return ($convenio && $convenio->activo) ? (float) $convenio->porcentaje_descuento : 0.0;
    }

    /**
     * Búsqueda por documento (exacto/prefijo, usa índice único), placa (índice único) o nombre.
     */
    public function scopeBuscar(Builder $q, ?string $termino): Builder
    {
        $t = trim((string) $termino);
        if ($t === '') {
            return $q;
        }

        if (ctype_digit($t)) {
            return $q->where('numero_documento', 'like', $t.'%');
        }

        $placa = Placa::normalizar($t);

        return $q->where(function (Builder $w) use ($t, $placa) {
            $w->where('nombres', 'like', '%'.$t.'%')
                ->orWhere('numero_documento', 'like', $t.'%');
            if (Placa::pareceValida($placa) || preg_match('/^[A-Z]{2,3}\d*$/', $placa)) {
                $w->orWhereHas('vehiculos', fn (Builder $v) => $v->where('placa', 'like', $placa.'%'));
            }
        });
    }
}
