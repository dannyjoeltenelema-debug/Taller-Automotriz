<?php

namespace App\Models\Inventario;

use App\Models\OtItem;
use App\Models\Sede;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Módulo Inventario — lo construye el otro programador. Solo modelo + relaciones. */
class Producto extends Model
{
    protected $table = 'productos';

    protected $fillable = [
        'categoria_id', 'codigo', 'nombre', 'descripcion', 'unidad_medida',
        'precio_costo', 'precio_venta', 'stock_minimo', 'activo',
    ];

    protected $casts = [
        'precio_costo' => 'decimal:2', 'precio_venta' => 'decimal:2', 'stock_minimo' => 'decimal:2', 'activo' => 'boolean',
    ];

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaProducto::class, 'categoria_id');
    }

    public function proveedores(): BelongsToMany
    {
        return $this->belongsToMany(Proveedor::class, 'producto_proveedor')
            ->withPivot(['precio_compra', 'codigo_proveedor'])
            ->withTimestamps();
    }

    /** Stock por sede. */
    public function inventarios(): HasMany
    {
        return $this->hasMany(Inventario::class);
    }

    public function sedes(): BelongsToMany
    {
        return $this->belongsToMany(Sede::class, 'inventarios')->withPivot('stock')->withTimestamps();
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoInventario::class);
    }

    /** Líneas de OT tipo=producto. */
    public function itemsOrden(): HasMany
    {
        return $this->hasMany(OtItem::class);
    }
}
