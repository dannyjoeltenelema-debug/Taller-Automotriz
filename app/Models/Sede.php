<?php

namespace App\Models;

use App\Models\Inventario\Inventario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sede extends Model
{
    protected $fillable = ['empresa_id', 'nombre', 'codigo', 'direccion', 'telefono', 'horario', 'activa'];

    protected $casts = ['activa' => 'boolean'];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function empleados(): HasMany
    {
        return $this->hasMany(Empleado::class);
    }

    public function ordenes(): HasMany
    {
        return $this->hasMany(OrdenTrabajo::class);
    }

    public function citas(): HasMany
    {
        return $this->hasMany(Cita::class);
    }

    /** Stock por sede (módulo Inventario). */
    public function inventarios(): HasMany
    {
        return $this->hasMany(Inventario::class);
    }
}
