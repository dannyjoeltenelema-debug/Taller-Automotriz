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
     * Franjas según config/taller.php (apertura, cierre y duración; por defecto 1 h: 08:00–09:00 … 13:00–14:00).
     *
     * @return list<string> horas de inicio, p. ej. ['08:00', '09:00', …]
     */
    public static function franjas(): array
    {
        $paso = max(5, (int) config('taller.citas.duracion_franja_min'));
        $fin = (int) config('taller.citas.hora_cierre') * 60;

        $franjas = [];
        for ($m = (int) config('taller.citas.hora_apertura') * 60; $m + $paso <= $fin; $m += $paso) {
            $franjas[] = sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
        }

        return $franjas;
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
        $inicio = (int) substr($hora, 0, 2) * 60 + (int) substr($hora, 3, 2);
        $fin = $inicio + max(5, (int) config('taller.citas.duracion_franja_min'));

        return sprintf('%02d:%02d–%02d:%02d', intdiv($inicio, 60), $inicio % 60, intdiv($fin, 60), $fin % 60);
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
