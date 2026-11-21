<?php

namespace Tests\Feature;

use App\Models\OrdenTrabajo;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Once;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Medición por pantalla (NO es una aserción; se excluye de la suite normal, grupo "medicion"):
 * consultas SQL, tiempo de respuesta (mediana de N cargas en caliente), tamaño del HTML y
 * tamaño del payload inicial de Livewire (atributos wire:snapshot).
 *
 * Uso:  php vendor/bin/phpunit --group=medicion   (MEDICION_ETIQUETA=antes|despues)
 * Resultado: storage/app/medicion/<etiqueta>.json y .md
 */
#[Group('medicion')]
class MedicionPantallasTest extends TestCase
{
    use RefreshDatabase;

    private const REPETICIONES = 21;

    public function test_medir_pantallas(): void
    {
        $this->seed();
        // Se mide el render, no el limitador del portal (30 peticiones/min por IP).
        $this->withoutMiddleware(ThrottleRequests::class);
        $admin = User::where('cedula', '1710000009')->first();
        $super = User::where('cedula', '1729057412')->first();
        $veh = Vehiculo::where('placa', 'PBA4521')->first();
        $ot = OrdenTrabajo::orderBy('id')->first();

        $pantallas = [
            ['Panel · Órdenes (listado)', '/panel/ordenes', $admin],
            ['Panel · Nueva OT', '/panel/ordenes/nueva', $admin],
            ['Panel · Editar OT', "/panel/ordenes/{$ot->id}/editar", $admin],
            ['Panel · Vehículos (listado)', '/panel/vehiculos', $admin],
            ['Panel · Nuevo vehículo', '/panel/vehiculos/nuevo', $admin],
            ['Panel · Ficha vehículo', "/panel/vehiculos/{$veh->id}", $admin],
            ['Panel · Editar vehículo', "/panel/vehiculos/{$veh->id}/editar", $admin],
            ['Panel · Clientes', '/panel/clientes', $admin],
            ['Panel · Empresas colaboradoras', '/panel/empresas-colaboradoras', $admin],
            ['Panel · Citas', '/panel/citas', $admin],
            ['Panel · Empleados', '/panel/empleados', $admin],
            ['Panel · Catálogos', '/panel/catalogos', $admin],
            ['Panel · Datos del taller', '/panel/configuracion', $super],
            ['Panel · Mi perfil', '/settings/profile', $admin],
            ['Sistema · Auditoría', '/panel/sistema/auditoria', $super],
            ['Sistema · Usuarios', '/panel/sistema/usuarios', $super],
            ['Sistema · Sesiones', '/panel/sistema/sesiones', $super],
            ['Sistema · Eliminados', '/panel/sistema/eliminados', $super],
            ['Sistema · Estado', '/panel/sistema/estado', $super],
            ['Sistema · Logs', '/panel/sistema/logs', $super],
            ['Portal · Inicio', '/', null],
            ['Portal · Vehículo (QR)', "/q/{$veh->qr_token}", null],
        ];

        $n = 0;
        DB::listen(function () use (&$n) {
            $n++;
        });

        $filas = [];
        foreach ($pantallas as [$nombre, $url, $user]) {
            if (! $this->existeRuta($url)) {
                $filas[] = ['pantalla' => $nombre, 'url' => $url, 'existe' => false];

                continue;
            }
            $this->cargar($url, $user); // calienta catálogos y vistas compiladas

            $tiempos = [];
            $consultas = null;
            $html = '';
            for ($i = 0; $i < self::REPETICIONES; $i++) {
                $n = 0;
                $t = hrtime(true);
                $res = $this->cargar($url, $user);
                $tiempos[] = (hrtime(true) - $t) / 1e6;
                $consultas ??= $n;
                $html = $res->getContent();
            }
            sort($tiempos);

            preg_match_all('/wire:snapshot="([^"]*)"/', $html, $m);
            $filas[] = [
                'pantalla' => $nombre, 'url' => $url, 'existe' => true,
                'consultas' => $consultas,
                'ms' => round($tiempos[intdiv(count($tiempos), 2)], 1),
                'html_kb' => round(strlen($html) / 1024, 1),
                'livewire_kb' => round(array_sum(array_map('strlen', $m[1] ?? [])) / 1024, 1),
                'status' => $res->getStatusCode(),
            ];
        }

        $etiqueta = env('MEDICION_ETIQUETA', 'medicion');
        $dir = storage_path('app/medicion');
        @mkdir($dir, 0777, true);
        file_put_contents("{$dir}/{$etiqueta}.json", json_encode($filas, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $md = "| Pantalla | Consultas | ms (mediana) | HTML KB | Livewire KB |\n|---|---:|---:|---:|---:|\n";
        foreach ($filas as $f) {
            $md .= $f['existe']
                ? "| {$f['pantalla']} | {$f['consultas']} | {$f['ms']} | {$f['html_kb']} | {$f['livewire_kb']} |\n"
                : "| {$f['pantalla']} | — | — | — | — |\n";
        }
        file_put_contents("{$dir}/{$etiqueta}.md", $md);
        fwrite(STDERR, "\n".$md);

        $this->assertNotEmpty($filas);
    }

    private function cargar(string $url, ?User $user)
    {
        Once::flush();
        $this->app->forgetScopedInstances();
        $this->app['auth']->forgetGuards();
        if ($user) {
            $this->actingAs($user);
        }

        return $this->get($url)->assertOk();
    }

    private function existeRuta(string $url): bool
    {
        try {
            Route::getRoutes()->match(Request::create($url));

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
