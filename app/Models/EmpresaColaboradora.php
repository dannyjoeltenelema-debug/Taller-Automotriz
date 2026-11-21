<?php

namespace App\Models;

use App\Models\Concerns\InvalidaCatalogos;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Convenio corporativo: sus clientes reciben porcentaje_descuento.
 * Se elimina con soft delete (no hay "activo"): ver App\Services\GestorConvenios.
 */
class EmpresaColaboradora extends Model
{
    use InvalidaCatalogos, SoftDeletes;

    protected $table = 'empresas_colaboradoras';

    protected $fillable = ['nombre', 'ruc', 'porcentaje_descuento', 'contacto', 'telefono'];

    protected $casts = ['porcentaje_descuento' => 'decimal:2'];

    public function clientes(): HasMany
    {
        return $this->hasMany(Cliente::class);
    }
}
