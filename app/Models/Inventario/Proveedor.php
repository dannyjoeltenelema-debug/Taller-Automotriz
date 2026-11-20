<?php

namespace App\Models\Inventario;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Módulo Inventario — lo construye el otro programador. Solo modelo + relaciones. */
class Proveedor extends Model
{
    protected $table = 'proveedores';

    protected $fillable = ['ruc', 'razon_social', 'contacto', 'telefono', 'email', 'direccion', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public function productos(): BelongsToMany
    {
        return $this->belongsToMany(Producto::class, 'producto_proveedor')
            ->withPivot(['precio_compra', 'codigo_proveedor'])
            ->withTimestamps();
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoInventario::class);
    }
}
