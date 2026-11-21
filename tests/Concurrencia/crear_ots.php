<?php

/**
 * Proceso hijo del test de concurrencia del código OT (ConcurrenciaCodigoOtTest, grupo "mysql").
 * Crea N órdenes, cada una en su propia transacción, con pausas aleatorias para que las
 * transacciones de los distintos procesos se intercalen. Imprime un código por línea.
 *
 * Uso (lo lanza el test, no se ejecuta a mano):
 *   php tests/Concurrencia/crear_ots.php <n> <sede_id> <vehiculo_id> <cliente_id> <asesor_id>
 */

use App\Models\OrdenTrabajo;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $n, $sede, $vehiculo, $cliente, $asesor] = $argv;

try {
    for ($i = 0; $i < (int) $n; $i++) {
        $orden = DB::transaction(function () use ($sede, $vehiculo, $cliente, $asesor) {
            $o = OrdenTrabajo::create([
                'sede_id' => $sede, 'vehiculo_id' => $vehiculo, 'cliente_id' => $cliente, 'asesor_id' => $asesor,
                'fecha_ingreso' => now(), 'kilometraje_ingreso' => 1, 'estado' => 'recibido',
                'subtotal' => 0, 'descuento' => 0, 'iva' => 0, 'total' => 0,
            ]);
            usleep(random_int(0, 15000)); // mantiene la transacción abierta un rato: fuerza la espera de los demás

            return $o;
        });
        echo $orden->codigo, PHP_EOL;
        usleep(random_int(0, 5000));
    }
} catch (Throwable $e) {
    fwrite(STDERR, get_class($e).': '.$e->getMessage().PHP_EOL);
    exit(1);
}
