<?php

namespace App\Support;

use App\Models\ChecklistItem;
use App\Models\Empleado;
use App\Models\Empresa;
use App\Models\EmpresaColaboradora;
use App\Models\Marca;
use App\Models\Modelo;
use App\Models\Sede;
use App\Models\Servicio;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Catálogos casi estáticos (empresa, servicios, sedes, convenios, marcas/modelos,
 * checklist y técnicos) en UNA sola clave de caché.
 *
 * - Una sola lectura de caché por request, sea cual sea el driver (con CACHE_STORE=database
 *   eso es 1 consulta en vez de 6; con file/redis, 0 consultas).
 * - Se guarda solo data escalar (arrays): no depende de unserialize de clases.
 * - Se invalida al guardar/eliminar cualquiera de esos modelos (trait InvalidaCatalogos),
 *   después del commit si hay transacción abierta. TTL de respaldo en config/taller.php.
 *
 * Registrado como `scoped` en el contenedor: el memo en memoria vive lo que dura el request.
 */
class Catalogos
{
    public const CLAVE = 'taller:catalogos:v2'; // v2: convenios sin "activo" (soft delete)

    private ?array $datos = null;

    // ───────────── API pública ─────────────

    public static function empresa(): ?Empresa
    {
        $a = self::get('empresa');

        return $a ? (new Empresa)->newFromBuilder($a) : null;
    }

    /** Servicios activos, por nombre. */
    public static function servicios(): Collection
    {
        return Servicio::hydrate(self::get('servicios'));
    }

    /** Todas las sedes (activas e inactivas), por nombre. */
    public static function sedes(): Collection
    {
        return Sede::hydrate(self::get('sedes'));
    }

    public static function sedesActivas(): Collection
    {
        return self::sedes()->where('activa', true)->values();
    }

    /** Empresas colaboradoras vigentes (las eliminadas no se incluyen), por nombre. */
    public static function convenios(): Collection
    {
        return EmpresaColaboradora::hydrate(self::get('convenios'));
    }

    /** Nombres de marcas, ordenados. */
    public static function marcas(): Collection
    {
        return collect(array_keys(self::get('marcas')));
    }

    /** Modelos de una marca (comparación sin distinguir mayúsculas, como la collation de MySQL). */
    public static function modelosDe(?string $marca): Collection
    {
        $buscada = mb_strtolower(trim((string) $marca));
        foreach (self::get('marcas') as $nombre => $modelos) {
            if (mb_strtolower($nombre) === $buscada) {
                return collect($modelos);
            }
        }

        return collect();
    }

    /** Ítems activos del checklist de recepción, en su orden. */
    public static function checklist(): Collection
    {
        return ChecklistItem::hydrate(self::get('checklist'));
    }

    /** Técnicos activos, por nombre. */
    public static function tecnicos(): Collection
    {
        return Empleado::hydrate(self::get('tecnicos'));
    }

    /** Invalida la caché (tras el commit si hay una transacción abierta). */
    public static function olvidar(): void
    {
        $limpiar = function () {
            Cache::forget(self::CLAVE);
            app(self::class)->datos = null;
        };

        $limpiar();                 // el request actual ve el cambio de inmediato
        DB::afterCommit($limpiar);  // y nadie re-cachea data previa al commit
    }

    // ───────────── Interno ─────────────

    private static function get(string $clave): mixed
    {
        $self = app(self::class);
        $self->datos ??= Cache::remember(self::CLAVE, config('taller.cache.ttl_seg'), fn () => self::construir());

        return $self->datos[$clave];
    }

    private static function filas($query): array
    {
        return $query->toBase()->get()->map(fn ($r) => (array) $r)->all();
    }

    private static function construir(): array
    {
        $listaMarcas = Marca::orderBy('nombre')->get(['id', 'nombre']);
        $marcas = $listaMarcas->mapWithKeys(fn ($m) => [$m->nombre => []])->all();
        $porId = $listaMarcas->pluck('nombre', 'id');
        foreach (Modelo::orderBy('nombre')->get(['marca_id', 'nombre']) as $mod) {
            $marcas[$porId[$mod->marca_id]][] = $mod->nombre;
        }

        $empresa = Empresa::query()->toBase()->first();

        return [
            'empresa' => $empresa ? (array) $empresa : null,
            'servicios' => self::filas(Servicio::where('activo', true)->orderBy('nombre')
                ->select(['id', 'nombre', 'descripcion', 'precio_referencial', 'activo'])),
            'sedes' => self::filas(Sede::orderBy('nombre')
                ->select(['id', 'nombre', 'codigo', 'direccion', 'telefono', 'horario', 'activa'])),
            'convenios' => self::filas(EmpresaColaboradora::orderBy('nombre')
                ->select(['id', 'nombre', 'porcentaje_descuento'])),
            'marcas' => $marcas,
            'checklist' => self::filas(ChecklistItem::where('activo', true)->orderBy('orden')->orderBy('id')
                ->select(['id', 'nombre', 'orden', 'activo'])),
            'tecnicos' => self::filas(Empleado::tecnicosActivos()->orderBy('nombres')
                ->select(['id', 'nombres', 'apellidos', 'especialidad', 'cargo', 'activo', 'sede_id'])),
        ];
    }
}
