<?php

namespace App\Models\Inventario;

use App\Models\Empleado;
use App\Models\OrdenTrabajo;
use App\Models\Sede;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kárdex. Entradas llevan proveedor_id; salidas por OT llevan orden_id.
 * Módulo Inventario — otro programador.
 */
class MovimientoInventario extends Model
{
    protected $table = 'movimientos_inventario';

    protected $fillable = [
        'producto_id', 'sede_id', 'tipo', 'cantidad', 'costo_unitario', 'stock_resultante',
        'proveedor_id', 'orden_id', 'empleado_id', 'nota',
    ];

    protected $casts = ['cantidad' => 'decimal:2', 'costo_unitario' => 'decimal:2', 'stock_resultante' => 'decimal:2'];

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function orden(): BelongsTo
    {
        return $this->belongsTo(OrdenTrabajo::class, 'orden_id');
    }

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado::class);
    }
}
