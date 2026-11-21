<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\InvalidaCatalogos;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Empleado extends Model
{
    use Auditable, InvalidaCatalogos; // la lista de técnicos activos está en la caché de catálogos

    public const CARGOS = [
        'admin' => 'Administrador',
        'asesor' => 'Asesor de servicio',
        'tecnico' => 'Técnico',
        'mecanico' => 'Mecánico',
    ];

    protected $fillable = [
        'sede_id', 'user_id', 'nombres', 'apellidos', 'cedula', 'telefono', 'cargo', 'especialidad', 'activo',
    ];

    protected $casts = ['activo' => 'boolean'];

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ordenesAsesor(): HasMany
    {
        return $this->hasMany(OrdenTrabajo::class, 'asesor_id');
    }

    public function ordenesTecnico(): HasMany
    {
        return $this->hasMany(OrdenTrabajo::class, 'tecnico_id');
    }

    /** Empleados activos que pueden ser técnicos de una OT (cargos en config/taller.php → ordenes.cargos_tecnico). */
    public function scopeTecnicosActivos(Builder $q): Builder
    {
        return $q->whereIn('cargo', config('taller.ordenes.cargos_tecnico'))->where('activo', true);
    }

    /** Empleados activos que pueden registrarse como asesor de una OT (ordenes.cargos_asesor). */
    public function scopeAsesoresActivos(Builder $q): Builder
    {
        return $q->whereIn('cargo', config('taller.ordenes.cargos_asesor'))->where('activo', true);
    }

    public function getNombreCompletoAttribute(): string
    {
        return trim($this->nombres.' '.$this->apellidos);
    }
}
