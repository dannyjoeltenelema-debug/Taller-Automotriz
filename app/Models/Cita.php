<?php

namespace App\Models;

use App\Support\Placa;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cita extends Model
{
    public const ESTADOS = ['pendiente' => 'Pendiente', 'confirmada' => 'Confirmada', 'atendida' => 'Atendida', 'cancelada' => 'Cancelada'];

    public const ORIGENES = ['qr_vehiculo' => 'QR / Portal', 'recepcion' => 'Recepción'];

    /** Franjas de 1 hora: 08:00–09:00 … 13:00–14:00 */
    public const FRANJAS = ['08:00', '09:00', '10:00', '11:00', '12:00', '13:00'];

    protected $fillable = [
        'sede_id', 'vehiculo_id', 'orden_id', 'placa', 'cliente_nombre', 'cliente_telefono',
        'fecha_deseada', 'hora_inicio', 'motivo', 'observacion', 'origen', 'estado',
    ];

    protected $casts = ['fecha_deseada' => 'date'];

    protected function placa(): Attribute
    {
        return Attribute::make(set: fn (?string $v) => Placa::normalizar($v));
    }

    public static function etiquetaFranja(string $hora): string
    {
        $h = (int) substr($hora, 0, 2);

        return sprintf('%02d:00–%02d:00', $h, $h + 1);
    }

    public function getFranjaAttribute(): string
    {
        return self::etiquetaFranja((string) $this->hora_inicio);
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class);
    }

    public function orden(): BelongsTo
    {
        return $this->belongsTo(OrdenTrabajo::class, 'orden_id');
    }
}
