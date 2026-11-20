<?php

namespace Database\Seeders;

use App\Models\Cita;
use App\Models\Cliente;
use App\Models\Empleado;
use App\Models\EmpresaColaboradora;
use App\Models\Modelo;
use App\Models\OrdenTrabajo;
use App\Models\Sede;
use App\Models\Servicio;
use App\Models\Vehiculo;
use App\Services\CalculadorDescuento;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Clientes, vehículos, OTs y citas de ejemplo (sin inventario). */
class DemoOperacionSeeder extends Seeder
{
    public function run(): void
    {
        $petro = EmpresaColaboradora::where('porcentaje_descuento', 10)->first();
        $taxis = EmpresaColaboradora::where('porcentaje_descuento', 15)->first();

        $clientes = [
            'juan' => ['cedula', '1722223334', 'Juan Carlos Pérez Andrade', '0991234567', 'jperez@correo.ec', null],
            'ana' => ['cedula', '0912345675', 'Ana Lucía Torres Villacís', '0987654321', 'anatorres@correo.ec', $petro->id],
            'trans' => ['ruc', '1791234567001', 'Transportes Andinos S.A.', '022334455', 'flota@transandinos.ec', $taxis->id],
            'roberto' => ['cedula', '1713579132', 'Roberto Vásquez Mora', '0976543210', null, $taxis->id],
            'comercial' => ['ruc', '0990011223001', 'Comercial El Ejido Cía. Ltda.', '042567890', 'compras@elejido.ec', null],
        ];
        $c = [];
        foreach ($clientes as $k => [$tipo, $doc, $nom, $tel, $mail, $conv]) {
            $c[$k] = Cliente::create([
                'tipo_documento' => $tipo, 'numero_documento' => $doc, 'nombres' => $nom, 'telefono' => $tel,
                'email' => $mail, 'empresa_colaboradora_id' => $conv, 'direccion' => $k === 'trans' ? 'Panamericana Norte km 7, Quito' : null,
            ]);
        }

        $mod = fn (string $marca, string $modelo) => Modelo::whereHas('marca', fn ($q) => $q->where('nombre', $marca))
            ->where('nombre', $modelo)->firstOrFail()->id;

        $v = [];
        foreach ([
            ['PBA-4521', 'juan', 'Chevrolet', 'Sail', 2019, 'Plata', 'gasolina', 'manual', '9BGKS48U0KG123456'],
            ['PCD-1234', 'ana', 'Kia', 'Sportage', 2021, 'Blanco', 'gasolina', 'automatica', null],
            ['PDE-5678', 'trans', 'Toyota', 'Hilux', 2020, 'Gris', 'diesel', 'manual', 'MR0FB8CD3L0123456'],
            ['PDE-5679', 'trans', 'Toyota', 'Hilux', 2022, 'Blanco', 'diesel', 'manual', null],
            ['PBX-7890', 'roberto', 'Hyundai', 'Accent', 2018, 'Amarillo', 'gasolina', 'manual', null],
            ['GSA-3456', 'comercial', 'Chevrolet', 'D-Max', 2017, 'Rojo', 'diesel', 'manual', null],
        ] as [$placa, $cli, $marca, $modelo, $anio, $color, $comb, $trans, $vin]) {
            $v[$placa] = Vehiculo::create([
                'placa' => $placa, 'cliente_id' => $c[$cli]->id, 'modelo_id' => $mod($marca, $modelo),
                'anio' => $anio, 'color' => $color, 'tipo_combustible' => $comb, 'transmision' => $trans, 'vin' => $vin,
            ]);
        }

        $norte = Sede::where('codigo', 'QN')->first();
        $sur = Sede::where('codigo', 'QS')->first();
        $asesor = Empleado::where('cargo', 'asesor')->first();
        $tecnicos = Empleado::tecnicosActivos()->orderBy('id')->get();
        $srv = Servicio::pluck('id', 'nombre');

        $ot = function (Vehiculo $veh, Sede $sede, string $fecha, int $km, array $servicios, array $libres, array $estados, ?Empleado $tec, ?string $cobro = null) use ($asesor, $srv) {
            DB::transaction(function () use ($veh, $sede, $fecha, $km, $servicios, $libres, $estados, $tec, $cobro, $asesor, $srv) {
                $items = [];
                foreach ($servicios as $nombre) {
                    $s = Servicio::find($srv[$nombre]);
                    $items[] = ['tipo' => 'servicio_fijo', 'servicio_id' => $s->id, 'descripcion' => $s->nombre, 'cantidad' => 1, 'precio_unitario' => $s->precio_referencial];
                }
                foreach ($libres as [$desc, $cant, $precio]) {
                    $items[] = ['tipo' => 'libre', 'servicio_id' => null, 'descripcion' => $desc, 'cantidad' => $cant, 'precio_unitario' => $precio];
                }
                $totales = app(CalculadorDescuento::class)->calcular($items, $veh->cliente);

                $orden = OrdenTrabajo::create([
                    'sede_id' => $sede->id, 'vehiculo_id' => $veh->id, 'cliente_id' => $veh->cliente_id,
                    'asesor_id' => $asesor->id, 'tecnico_id' => $tec?->id, 'fecha_ingreso' => $fecha,
                    'kilometraje_ingreso' => $km, 'proximo_kilometraje' => $km + $veh->intervalo_mantenimiento_km,
                    'nivel_combustible' => '1/2', 'observaciones_recepcion' => 'Vehículo ingresa por mantenimiento.',
                    'checklist' => [
                        ['nombre' => 'Luces delanteras y posteriores', 'ok' => true, 'nota' => ''],
                        ['nombre' => 'Golpes o rayones visibles', 'ok' => false, 'nota' => 'Rayón leve puerta posterior derecha'],
                    ],
                    'estado' => 'recibido',
                ] + collect($totales)->only(['subtotal', 'descuento', 'iva', 'total'])->all());

                foreach ($items as $it) {
                    $orden->items()->create($it + ['subtotal' => CalculadorDescuento::subtotalLinea($it['cantidad'], $it['precio_unitario'])]);
                }

                foreach ($estados as $e) {
                    $orden->update(['estado' => $e]);
                }
                if ($cobro) {
                    $orden->update(['estado_cobro' => 'pagado', 'forma_cobro' => $cobro]);
                }
                if ($orden->estado === 'entregado') {
                    $orden->update(['fecha_entrega' => (new \DateTime($fecha))->modify('+1 day')]);
                }
            });
        };

        // Historial (entregadas)
        $ot($v['PBA-4521'], $norte, now()->subMonths(7)->format('Y-m-d 09:15'), 40210, ['Cambio de aceite y filtro'], [['Aceite 10W-30 sintético (galón)', 1, 32.50]], ['en_proceso', 'listo_entrega', 'entregado'], $tecnicos[0], 'efectivo');
        $ot($v['PBA-4521'], $norte, now()->subMonths(2)->format('Y-m-d 10:00'), 45380, ['Cambio de aceite y filtro', 'Alineación y balanceo'], [], ['en_proceso', 'listo_entrega', 'entregado'], $tecnicos[0], 'tarjeta');
        $ot($v['PDE-5678'], $sur, now()->subDays(20)->format('Y-m-d 08:30'), 98120, ['Mantenimiento de frenos', 'Cambio de pastillas de freno'], [['Rectificación de discos (par)', 1, 40.00]], ['en_proceso', 'listo_entrega', 'entregado'], $tecnicos[2], 'transferencia');
        // Activas
        $ot($v['PCD-1234'], $norte, now()->subDays(1)->format('Y-m-d 11:20'), 22500, ['Mantenimiento preventivo 5.000 km'], [['Limpieza de cuerpo de aceleración', 1, 15.00]], ['en_proceso', 'listo_entrega'], $tecnicos[1]);
        $ot($v['PDE-5679'], $sur, now()->format('Y-m-d 08:45'), 31050, ['ABC de motor', 'Diagnóstico por escáner'], [], ['en_proceso'], $tecnicos[2]);
        $ot($v['PBX-7890'], $norte, now()->format('Y-m-d 09:30'), 120400, ['Revisión de suspensión'], [['Cambio de terminales de dirección (mano de obra)', 2, 12.50]], [], null);

        // Citas
        Cita::create([
            'sede_id' => $norte->id, 'vehiculo_id' => $v['PBA-4521']->id, 'placa' => 'PBA4521', 'cliente_nombre' => 'Juan Carlos Pérez Andrade',
            'cliente_telefono' => '0991234567', 'fecha_deseada' => now()->addDays(2)->toDateString(), 'hora_inicio' => '09:00',
            'motivo' => 'Cambio de aceite', 'observacion' => 'Escaneó el QR del parabrisas', 'origen' => 'qr_vehiculo',
        ]);
        Cita::create([
            'sede_id' => $norte->id, 'vehiculo_id' => $v['GSA-3456']->id, 'placa' => 'GSA3456', 'cliente_nombre' => 'Comercial El Ejido Cía. Ltda.',
            'cliente_telefono' => '042567890', 'fecha_deseada' => now()->addDays(3)->toDateString(), 'hora_inicio' => '11:00',
            'motivo' => 'Ruido en la suspensión', 'origen' => 'recepcion', 'estado' => 'confirmada',
        ]);
        Cita::create([
            'sede_id' => $sur->id, 'placa' => 'PCX1020', 'cliente_nombre' => 'Paola Jiménez Ortiz',
            'cliente_telefono' => '0984455667', 'fecha_deseada' => now()->addDays(1)->toDateString(), 'hora_inicio' => '08:00',
            'motivo' => 'Revisión de frenos', 'observacion' => 'Cliente nuevo, llamó por teléfono', 'origen' => 'recepcion',
        ]);
    }
}
