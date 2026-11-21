<?php

namespace App\Models;

use App\Support\Placa;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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
        'tipo_combustible' => 'gasolina',
        'transmision' => 'manual',
        'activo' => true,
    ];

    public function __construct(array $attributes = [])
    {
        // Intervalo por defecto configurable (config/taller.php); un default de propiedad no puede leer config().
        $this->attributes['intervalo_mantenimiento_km'] ??= config('taller.mantenimiento.intervalo_km');
        parent::__construct($attributes);
    }

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
        return $this->proximoKilometrajeDesde($this->ultimaOrden());
    }

    /** Igual que proximoKilometraje() pero con la última OT ya cargada (evita otra consulta). */
    public function proximoKilometrajeDesde(?OrdenTrabajo $ultima): ?int
    {
        if ($ultima?->proximo_kilometraje) {
            return (int) $ultima->proximo_kilometraje;
        }

        return $this->kilometraje_actual ? $this->kilometraje_actual + $this->intervalo_mantenimiento_km : null;
    }

    /**
     * Ficha del portal público: vehículo + modelo + marca + propietario en UNA consulta (JOIN).
     * Devuelve el modelo con las relaciones modelo.marca y cliente ya asignadas (solo las columnas
     * que muestra el portal), así la vista no dispara consultas adicionales.
     *
     * @throws ModelNotFoundException
     */
    public static function paraPortal(string $token): self
    {
        $v = static::query()
            ->join('modelos', 'modelos.id', '=', 'vehiculos.modelo_id')
            ->join('marcas', 'marcas.id', '=', 'modelos.marca_id')
            ->join('clientes', 'clientes.id', '=', 'vehiculos.cliente_id')
            ->where('vehiculos.qr_token', $token)
            ->where('vehiculos.activo', true)
            ->select([
                'vehiculos.*',
                'modelos.nombre as _modelo_nombre', 'marcas.id as _marca_id', 'marcas.nombre as _marca_nombre',
                'clientes.nombres as _cliente_nombres', 'clientes.numero_documento as _cliente_documento',
            ])
            ->firstOrFail();

        $a = $v->getAttributes();
        $v->setRawAttributes(array_filter($a, fn ($k) => ! str_starts_with($k, '_'), ARRAY_FILTER_USE_KEY), true);

        $marca = (new Marca)->newFromBuilder(['id' => $a['_marca_id'], 'nombre' => $a['_marca_nombre']]);
        $modelo = (new Modelo)->newFromBuilder(['id' => $v->modelo_id, 'marca_id' => $a['_marca_id'], 'nombre' => $a['_modelo_nombre']]);
        $cliente = (new Cliente)->newFromBuilder([
            'id' => $v->cliente_id, 'nombres' => $a['_cliente_nombres'], 'numero_documento' => $a['_cliente_documento'],
        ]);

        return $v->setRelation('modelo', $modelo->setRelation('marca', $marca))->setRelation('cliente', $cliente);
    }

    public static function porPlaca(?string $placa): ?self
    {
        return static::where('placa', Placa::normalizar($placa))->first();
    }
}
