<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\Placa;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cita extends Model
{
    use Auditable;

    public const ESTADOS = ['pendiente' => 'Pendiente', 'confirmada' => 'Confirmada', 'atendida' => 'Atendida', 'cancelada' => 'Cancelada'];

    public const ORIGENES = ['qr_vehiculo' => 'QR / Portal', 'recepcion' => 'Recepción'];

    protected $fillable = [
        'sede_id', 'vehiculo_id', 'orden_id', 'placa', 'cliente_nombre', 'cliente_telefono',
        'fecha_deseada', 'hora_inicio', 'motivo', 'observacion', 'origen', 'estado',
    ];

    protected $casts = ['fecha_deseada' => 'date'];

    protected function placa(): Attribute
    {
        return Attribute::make(set: fn (?string $v) => Placa::normalizar($v));
    }

    /**
     * Franjas de 1 hora según config/taller.php (por defecto 08:00–09:00 … 13:00–14:00).
     *
     * @return list<string> horas de inicio, p. ej. ['08:00', '09:00', …]
     */
    public static function franjas(): array
    {
        return array_map(
            fn (int $h) => sprintf('%02d:00', $h),
            range(config('taller.citas.hora_apertura'), config('taller.citas.hora_cierre') - 1)
        );
    }

    /** Citas de un día. Rango semiabierto (no DATE(col)) para que MySQL use el índice de fecha_deseada. */
    public function scopeDelDia(Builder $q, string $fecha): Builder
    {
        $dia = Carbon::parse($fecha)->startOfDay();

        return $q->where('fecha_deseada', '>=', $dia->toDateString())
            ->where('fecha_deseada', '<', $dia->copy()->addDay()->toDateString());
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
