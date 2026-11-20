<?php

namespace App\Models\Inventario;

use App\Models\Sede;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Stock de un producto en una sede. Módulo Inventario — otro programador. */
class Inventario extends Model
{
    protected $table = 'inventarios';

    protected $fillable = ['producto_id', 'sede_id', 'stock'];

    protected $casts = ['stock' => 'decimal:2'];

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }
}
