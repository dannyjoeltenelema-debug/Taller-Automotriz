<?php

namespace App\Models;

use App\Support\Placa;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehiculo extends Model
{
    public const COMBUSTIBLES = [
        'gasolina' => 'Gasolina', 'diesel' => 'Diésel', 'hibrido' => 'Híbrido', 'electrico' => 'Eléctrico', 'glp' => 'GLP',
    ];

    public const TRANSMISIONES = ['manual' => 'Manual', 'automatica' => 'Automática'];

    protected $fillable = [
        'placa', 'cliente_id', 'modelo_id', 'anio', 'color', 'vin', 'numero_motor',
        'tipo_combustible', 'transmision', 'intervalo_mantenimiento_km', 'kilometraje_actual', 'activo',
    ];

    protected $casts = ['activo' => 'boolean'];

    /** Defaults en memoria (iguales a los de la migración) para instancias recién creadas. */
    protected $attributes = [
        'intervalo_mantenimiento_km' => 5000,
        'tipo_combustible' => 'gasolina',
        'transmision' => 'manual',
        'activo' => true,
    ];

    /** Siempre se guarda normalizada: PBA4521. */
    protected function placa(): Attribute
    {
        return Attribute::make(set: fn (?string $v) => Placa::normalizar($v));
    }

    public function getPlacaFormateadaAttribute(): string
    {
        return Placa::formatear($this->placa);
    }

    public function getDescripcionAttribute(): string
    {
        $this->loadMissing('modelo.marca');

        return trim(($this->modelo?->marca?->nombre ?? '').' '.($this->modelo?->nombre ?? '').' '.$this->anio);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function modelo(): BelongsTo
    {
        return $this->belongsTo(Modelo::class);
    }

    public function ordenes(): HasMany
    {
        return $this->hasMany(OrdenTrabajo::class);
    }

    public function citas(): HasMany
    {
        return $this->hasMany(Cita::class);
    }

    /** Última OT no cancelada (para próximo mantenimiento). */
    public function ultimaOrden(): ?OrdenTrabajo
    {
        return $this->ordenes()->where('estado', '!=', 'cancelado')->latest('fecha_ingreso')->first();
    }

    public function proximoKilometraje(): ?int
    {
        $ultima = $this->ultimaOrden();
        if ($ultima?->proximo_kilometraje) {
            return (int) $ultima->proximo_kilometraje;
        }

        return $this->kilometraje_actual ? $this->kilometraje_actual + $this->intervalo_mantenimiento_km : null;
    }

    public static function porPlaca(?string $placa): ?self
    {
        return static::where('placa', Placa::normalizar($placa))->first();
    }
}
