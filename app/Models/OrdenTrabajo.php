<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Inventario\MovimientoInventario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OrdenTrabajo extends Model
{
    use Auditable;

    protected $table = 'ordenes_trabajo';

    public const ESTADOS = [
        'recibido' => 'Recibido',
        'en_proceso' => 'En proceso',
        'listo_entrega' => 'Listo para entrega',
        'entregado' => 'Entregado',
        'cancelado' => 'Cancelado',
    ];

    public const ESTADOS_ACTIVOS = ['recibido', 'en_proceso', 'listo_entrega'];

    public const NIVELES_COMBUSTIBLE = ['reserva' => 'Reserva', '1/4' => '1/4', '1/2' => '1/2', '3/4' => '3/4', 'lleno' => 'Lleno'];

    public const FORMAS_COBRO = ['efectivo' => 'Efectivo', 'transferencia' => 'Transferencia', 'tarjeta' => 'Tarjeta'];

    protected $fillable = [
        'codigo', 'sede_id', 'vehiculo_id', 'cliente_id', 'asesor_id', 'tecnico_id',
        'fecha_ingreso', 'fecha_entrega', 'kilometraje_ingreso', 'proximo_kilometraje', 'nivel_combustible',
        'observaciones_recepcion', 'checklist', 'diagnostico', 'estado',
        'subtotal', 'descuento', 'iva', 'iva_porcentaje', 'total', 'estado_cobro', 'forma_cobro',
    ];

    protected $casts = [
        'fecha_ingreso' => 'datetime',
        'fecha_entrega' => 'datetime',
        'checklist' => 'array',
        'subtotal' => 'decimal:2',
        'descuento' => 'decimal:2',
        'iva' => 'decimal:2',
        'iva_porcentaje' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    /** Nota opcional que el observer guarda junto al cambio de estado (no es columna). */
    public ?string $notaCambioEstado = null;

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function asesor(): BelongsTo
    {
        return $this->belongsTo(Empleado::class, 'asesor_id');
    }

    public function tecnico(): BelongsTo
    {
        return $this->belongsTo(Empleado::class, 'tecnico_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OtItem::class, 'orden_id');
    }

    public function historialEstados(): HasMany
    {
        return $this->hasMany(OtEstado::class, 'orden_id')->orderBy('created_at')->orderBy('id');
    }

    public function cita(): HasOne
    {
        return $this->hasOne(Cita::class, 'orden_id');
    }

    public function factura(): HasOne
    {
        return $this->hasOne(Factura::class, 'orden_id');
    }

    /** Salidas de stock asociadas (módulo Inventario). */
    public function movimientosInventario(): HasMany
    {
        return $this->hasMany(MovimientoInventario::class, 'orden_id');
    }

    public function scopeActivas(Builder $q): Builder
    {
        return $q->whereIn('estado', self::ESTADOS_ACTIVOS);
    }

    // ───────────── Transiciones de estado (config/taller.php → ordenes.transiciones) ─────────────

    /** @return list<string> */
    public static function transicionesDesde(string $estado): array
    {
        return config("taller.ordenes.transiciones.{$estado}", []);
    }

    /** Estado final (entregado, cancelado): sin transiciones configuradas. */
    public static function esEstadoFinal(string $estado): bool
    {
        return array_key_exists($estado, self::ESTADOS) && self::transicionesDesde($estado) === [];
    }

    /**
     * Estados a los que $user puede pasar esta OT (sin incluir el actual). Una OT nueva parte de "recibido".
     * Desde un estado final solo el superadmin puede reabrirla (al estado ordenes.reabrir_a).
     *
     * @return list<string>
     */
    public function estadosSiguientes(?User $user): array
    {
        $actual = $this->exists ? $this->getRawOriginal('estado') : 'recibido';

        if (self::esEstadoFinal($actual)) {
            return $user?->hasRole('superadmin') ? [config('taller.ordenes.reabrir_a')] : [];
        }

        return self::transicionesDesde($actual);
    }

    /** @return list<string> estado actual + siguientes (opciones de un selector). */
    public function estadosElegibles(?User $user): array
    {
        $actual = $this->exists ? $this->getRawOriginal('estado') : 'recibido';

        return array_values(array_unique([$actual, ...$this->estadosSiguientes($user)]));
    }

    public function puedePasarA(string $nuevo, ?User $user): bool
    {
        return in_array($nuevo, $this->estadosElegibles($user), true);
    }

    public function getEstadoLabelAttribute(): string
    {
        return self::ESTADOS[$this->estado] ?? $this->estado;
    }
}
