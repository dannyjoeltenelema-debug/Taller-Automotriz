<?php

namespace Tests\Feature;

use App\Models\Empleado;
use App\Models\OrdenTrabajo;
use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Concurrencia REAL del código OT: varios procesos PHP crean OT a la vez contra MySQL.
 * Solo corre con phpunit.mysql.xml (SQLite no tiene bloqueos de fila ni conexiones paralelas).
 *
 * No usa RefreshDatabase: los procesos hijos necesitan ver datos confirmados. Deja la BD
 * de tests migrada desde cero y marca el estado para que el siguiente test vuelva a migrar.
 */
#[Group('mysql')]
class ConcurrenciaCodigoOtTest extends TestCase
{
    private const PROCESOS = 4;

    private const OT_POR_PROCESO = 10;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Solo MySQL (php artisan test --configuration=phpunit.mysql.xml).');
        }

        $this->artisan('migrate:fresh', ['--seed' => true, '--force' => true]);
    }

    protected function tearDown(): void
    {
        RefreshDatabaseState::$migrated = false; // los datos de este test quedaron confirmados
        parent::tearDown();
    }

    public function test_creaciones_en_paralelo_no_duplican_codigos_ni_producen_deadlocks(): void
    {
        $vehiculo = Vehiculo::firstOrFail();
        $asesor = Empleado::where('cargo', 'asesor')->firstOrFail();
        $antes = OrdenTrabajo::count();

        $args = [self::OT_POR_PROCESO, $asesor->sede_id, $vehiculo->id, $vehiculo->cliente_id, $asesor->id];
        $env = array_merge(getenv(), [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => config('database.connections.mysql.host'),
            'DB_PORT' => (string) config('database.connections.mysql.port'),
            'DB_DATABASE' => config('database.connections.mysql.database'),
            'DB_USERNAME' => config('database.connections.mysql.username'),
            'DB_PASSWORD' => (string) config('database.connections.mysql.password'),
            'CACHE_STORE' => 'array',
        ]);

        $procesos = [];
        for ($p = 0; $p < self::PROCESOS; $p++) {
            $cmd = array_merge([PHP_BINARY, base_path('tests/Concurrencia/crear_ots.php')], array_map('strval', $args));
            $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path(), $env);
            $this->assertIsResource($proc);
            $procesos[] = [$proc, $pipes];
        }

        $codigos = [];
        $errores = [];
        foreach ($procesos as [$proc, $pipes]) {
            $salida = stream_get_contents($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $codigoSalida = proc_close($proc);
            if ($codigoSalida !== 0 || trim($error) !== '') {
                $errores[] = "salida {$codigoSalida}: ".trim($error);
            }
            array_push($codigos, ...array_filter(array_map('trim', explode("\n", $salida))));
        }

        $this->assertSame([], $errores, "Algún proceso falló (¿deadlock?):\n".implode("\n", $errores));

        $total = self::PROCESOS * self::OT_POR_PROCESO;
        $this->assertCount($total, $codigos);
        $this->assertCount($total, array_unique($codigos), 'Se generaron códigos OT duplicados.');
        $this->assertSame($antes + $total, OrdenTrabajo::count());

        // Secuencia continua: sin huecos ni saltos.
        $numeros = OrdenTrabajo::where('codigo', 'like', 'OT-'.now()->year.'-%')->pluck('codigo')
            ->map(fn ($c) => (int) substr($c, strrpos($c, '-') + 1))->sort()->values();
        $this->assertSame(range(1, $numeros->count()), $numeros->all());
    }
}
