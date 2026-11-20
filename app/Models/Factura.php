<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Facturación electrónica (SRI): tabla y modelo listos, SIN UI en v1.
 * En v1 el cobro es solo estado_cobro + forma_cobro en la OT.
 */
class Factura extends Model
{
    protected $fillable = [
        'orden_id', 'cliente_id', 'sede_id', 'fecha_emision', 'subtotal', 'descuento', 'iva', 'total', 'estado',
        'establecimiento', 'punto_emision', 'secuencial', 'clave_acceso', 'numero_autorizacion',
        'fecha_autorizacion', 'ambiente_sri', 'estado_sri',
    ];

    protected $casts = [
        'fecha_emision' => 'date',
        'fecha_autorizacion' => 'datetime',
        'subtotal' => 'decimal:2', 'descuento' => 'decimal:2', 'iva' => 'decimal:2', 'total' => 'decimal:2',
    ];

    public function orden(): BelongsTo
    {
        return $this->belongsTo(OrdenTrabajo::class, 'orden_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(FacturaDetalle::class);
    }
}
