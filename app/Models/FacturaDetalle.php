<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacturaDetalle extends Model
{
    protected $fillable = ['factura_id', 'codigo_principal', 'descripcion', 'cantidad', 'precio_unitario', 'descuento', 'subtotal'];

    protected $casts = [
        'cantidad' => 'decimal:2', 'precio_unitario' => 'decimal:2', 'descuento' => 'decimal:2', 'subtotal' => 'decimal:2',
    ];

    public function factura(): BelongsTo
    {
        return $this->belongsTo(Factura::class);
    }
}
