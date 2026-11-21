<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\InvalidaCatalogos;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Servicio extends Model
{
    use Auditable, InvalidaCatalogos;

    protected $fillable = ['nombre', 'descripcion', 'precio_referencial', 'activo'];

    protected $casts = ['precio_referencial' => 'decimal:2', 'activo' => 'boolean'];

    public function items(): HasMany
    {
        return $this->hasMany(OtItem::class);
    }
}
