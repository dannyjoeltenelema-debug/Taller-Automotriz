<?php

namespace App\Models;

use App\Models\Concerns\InvalidaCatalogos;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Convenio corporativo: sus clientes reciben porcentaje_descuento. */
class EmpresaColaboradora extends Model
{
    use InvalidaCatalogos;

    protected $table = 'empresas_colaboradoras';

    protected $fillable = ['nombre', 'ruc', 'porcentaje_descuento', 'contacto', 'telefono', 'activo'];

    protected $casts = ['porcentaje_descuento' => 'decimal:2', 'activo' => 'boolean'];

    public function clientes(): HasMany
    {
        return $this->hasMany(Cliente::class);
    }
}
