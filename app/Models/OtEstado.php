<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Trazabilidad de cambios de estado de una OT. */
class OtEstado extends Model
{
    protected $table = 'ot_estados';

    public const UPDATED_AT = null;

    protected $fillable = ['orden_id', 'estado_anterior', 'estado_nuevo', 'empleado_id', 'nota'];

    protected $casts = ['created_at' => 'datetime'];

    public function orden(): BelongsTo
    {
        return $this->belongsTo(OrdenTrabajo::class, 'orden_id');
    }

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado::class);
    }
}
