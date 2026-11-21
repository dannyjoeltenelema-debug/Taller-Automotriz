<?php

namespace App\Livewire\Sistema;

use App\Models\Auditoria;
use App\Support\Catalogos;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Estado del sistema: versiones, entorno y tamaño de tablas, más limpiar cachés de la app.
 * Nunca ejecuta migraciones ni nada destructivo.
 */
#[Layout('components.layouts.panel')]
#[Title('Estado del sistema')]
#[Lazy]
class Estado extends Component
{
    use SoloSuperadmin;

    public function placeholder()
    {
        return view('livewire.sistema.partials.cargando', ['titulo' => 'Estado del sistema']);
    }

    public function limpiarCaches(): void
    {
        Catalogos::olvidar();               // caché de catálogos (servicios, sedes, convenios, marcas…)
        Artisan::call('view:clear');
        Artisan::call('config:clear');
        Auditoria::registrar('cache_limpiada', null, null, ['caches' => 'catalogos, view, config']);

        $this->dispatch('toast', mensaje: 'Cachés limpiadas: catálogos, vistas compiladas y configuración.');
    }

    private function versionBd(): string
    {
        return match (DB::connection()->getDriverName()) {
            'mysql', 'mariadb' => 'MySQL '.(DB::selectOne('select version() as v')->v ?? '?'),
            'sqlite' => 'SQLite '.(DB::selectOne('select sqlite_version() as v')->v ?? '?'),
            default => DB::connection()->getDriverName(),
        };
    }

    /** @return list<object{tabla: string, filas: int, mb: float}>|null null si el motor no lo informa */
    private function tablas(): ?array
    {
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return null;
        }

        return DB::select(
            'select table_name as tabla, table_rows as filas, round((data_length + index_length) / 1048576, 2) as mb
               from information_schema.tables where table_schema = ? order by (data_length + index_length) desc',
            [DB::connection()->getDatabaseName()]
        );
    }

    public function render()
    {
        return view('livewire.sistema.estado', [
            'info' => [
                'PHP' => PHP_VERSION,
                'Laravel' => Application::VERSION,
                'Base de datos' => $this->versionBd(),
                'Entorno' => app()->environment(),
                'Depuración (APP_DEBUG)' => config('app.debug') ? 'Activada' : 'Desactivada',
                'Zona horaria' => config('app.timezone'),
                'Caché (CACHE_STORE)' => config('cache.default'),
                'Sesiones (SESSION_DRIVER)' => config('session.driver'),
                'Colas (QUEUE_CONNECTION)' => config('queue.default'),
                'Configuración cacheada' => app()->configurationIsCached() ? 'Sí' : 'No',
                'Rutas cacheadas' => app()->routesAreCached() ? 'Sí' : 'No',
            ],
            'tablas' => $this->tablas(),
        ]);
    }
}
