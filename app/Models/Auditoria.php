<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Registro de auditoría (solo inserción). Se escribe con Auditoria::registrar():
 * un INSERT por acción, sin SELECT previo.
 */
class Auditoria extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'auditorias';

    protected $guarded = ['id'];

    protected $casts = ['valores_antes' => 'array', 'valores_despues' => 'array', 'created_at' => 'datetime'];

    public const ACCIONES = [
        'creado' => 'Creó', 'actualizado' => 'Modificó', 'eliminado' => 'Eliminó', 'restaurado' => 'Restauró',
        'login' => 'Inició sesión', 'logout' => 'Cerró sesión', 'login_fallido' => 'Acceso fallido',
        'reabierto' => 'Reabrió OT', 'sesion_cerrada' => 'Cerró una sesión', 'cache_limpiada' => 'Limpió cachés',
        'bloqueado' => 'Bloqueó acceso', 'desbloqueado' => 'Desbloqueó acceso', 'password_restablecida' => 'Restableció contraseña',
        'exportado' => 'Exportó',
    ];

    /** Nombre legible del módulo según el modelo auditado. */
    public const MODULOS = [
        OrdenTrabajo::class => 'Órdenes de trabajo',
        Vehiculo::class => 'Vehículos',
        Cliente::class => 'Clientes',
        EmpresaColaboradora::class => 'Empresas colaboradoras',
        Empleado::class => 'Empleados',
        Cita::class => 'Citas',
        Servicio::class => 'Catálogos: servicios',
        ChecklistItem::class => 'Catálogos: checklist',
        Marca::class => 'Catálogos: marcas',
        Modelo::class => 'Catálogos: modelos',
        Sede::class => 'Sedes',
        Empresa::class => 'Datos del taller',
        User::class => 'Usuarios',
    ];

    /** Campos que nunca se guardan en claro (solo se registra que cambiaron). */
    public const CAMPOS_SECRETOS = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    /** Campos que no aportan al diff. */
    public const CAMPOS_IGNORADOS = ['created_at', 'updated_at'];

    public const OCULTO = '[oculto]';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Inserta un registro: 1 INSERT, sin SELECT. IP y user agent salen de la petición actual.
     */
    public static function registrar(string $accion, ?Model $modelo = null, ?array $antes = null, ?array $despues = null, ?int $userId = null): void
    {
        $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();

        static::query()->insert([
            'user_id' => $userId ?? auth()->id(),
            'accion' => $accion,
            'auditable_type' => $modelo ? $modelo::class : null,
            'auditable_id' => $modelo?->getKey(),
            'valores_antes' => $antes === null ? null : json_encode(self::limpiar($antes), JSON_UNESCAPED_UNICODE),
            'valores_despues' => $despues === null ? null : json_encode(self::limpiar($despues), JSON_UNESCAPED_UNICODE),
            'ip' => $request?->ip(),
            'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 255) : null,
            // La sede sale del propio registro si la tiene (OT, cita, empleado): sin consultas extra.
            'sede_id' => $modelo?->getAttributes()['sede_id'] ?? null,
            'created_at' => Carbon::now(),
        ]);
    }

    /** Reemplaza secretos por [oculto] y normaliza fechas/objetos a texto. */
    public static function limpiar(array $valores): array
    {
        foreach ($valores as $campo => $valor) {
            if (in_array($campo, self::CAMPOS_SECRETOS, true)) {
                $valores[$campo] = self::OCULTO;
            } elseif ($valor instanceof \DateTimeInterface) {
                $valores[$campo] = Carbon::instance($valor)->format('Y-m-d H:i:s');
            } elseif ($valor instanceof \BackedEnum) {
                $valores[$campo] = $valor->value;
            } elseif (is_object($valor) && ! $valor instanceof \JsonSerializable) {
                $valores[$campo] = method_exists($valor, '__toString') ? (string) $valor : null;
            }
        }

        return $valores;
    }

    public function getModuloAttribute(): string
    {
        if (! $this->auditable_type) {
            return 'Acceso';
        }

        return self::MODULOS[$this->auditable_type] ?? class_basename($this->auditable_type);
    }

    public function getAccionLabelAttribute(): string
    {
        return self::ACCIONES[$this->accion] ?? $this->accion;
    }

    /**
     * Diff legible campo por campo: [campo => [antes, después]] (solo los campos registrados).
     *
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    public function diff(): array
    {
        $antes = $this->valores_antes ?? [];
        $despues = $this->valores_despues ?? [];

        $diff = [];
        foreach (array_unique([...array_keys($antes), ...array_keys($despues)]) as $campo) {
            $diff[$campo] = [$antes[$campo] ?? null, $despues[$campo] ?? null];
        }

        return $diff;
    }

    /** Valor de un campo del diff como texto corto. */
    public static function textoValor(mixed $v): string
    {
        return match (true) {
            $v === null || $v === '' => '—',
            is_bool($v) => $v ? 'Sí' : 'No',
            is_array($v) => json_encode($v, JSON_UNESCAPED_UNICODE),
            default => (string) $v,
        };
    }

    /**
     * Mismos filtros en la pantalla y en el PDF.
     *
     * @param  array{usuario?: mixed, modulo?: ?string, accion?: ?string, desde?: ?string, hasta?: ?string, buscar?: ?string}  $f
     */
    public function scopeFiltrar(Builder $q, array $f): Builder
    {
        return $q
            ->when($f['usuario'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->when($f['modulo'] ?? null, fn ($q, $v) => $v === 'acceso'
                ? $q->whereIn('accion', ['login', 'logout', 'login_fallido'])
                : $q->where('auditable_type', $v)->whereNotIn('accion', ['login', 'logout']))
            ->when($f['accion'] ?? null, fn ($q, $v) => $q->where('accion', $v))
            ->when($f['desde'] ?? null, fn ($q, $v) => $q->where('created_at', '>=', Carbon::parse($v)->startOfDay()))
            ->when($f['hasta'] ?? null, fn ($q, $v) => $q->where('created_at', '<', Carbon::parse($v)->addDay()->startOfDay()))
            ->when(trim((string) ($f['buscar'] ?? '')) !== '', function ($q) use ($f) {
                $t = trim((string) $f['buscar']);
                $q->where(function ($w) use ($t) {
                    $w->where('ip', 'like', $t.'%')
                        ->orWhereIn('user_id', User::select('id')->where('name', 'like', '%'.$t.'%')->orWhere('cedula', 'like', $t.'%'));
                    if (ctype_digit($t)) {
                        $w->orWhere('auditable_id', (int) $t);
                    }
                });
            });
    }
}
