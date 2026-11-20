<?php

namespace App\Models;

use App\Models\Inventario\Producto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OtItem extends Model
{
    protected $table = 'ot_items';

    /**
     * 'producto' existe en BD pero NO se usa en la UI de Órdenes.
     * TODO INVENTARIO: el módulo Inventario agrega líneas tipo=producto que descuentan stock.
     */
    public const TIPOS = ['servicio_fijo' => 'Servicio fijo', 'libre' => 'Libre', 'producto' => 'Producto'];

    protected $fillable = ['orden_id', 'tipo', 'servicio_id', 'producto_id', 'descripcion', 'cantidad', 'precio_unitario', 'subtotal'];

    protected $casts = ['cantidad' => 'decimal:2', 'precio_unitario' => 'decimal:2', 'subtotal' => 'decimal:2'];

    public function orden(): BelongsTo
    {
        return $this->belongsTo(OrdenTrabajo::class, 'orden_id');
    }

    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
}
