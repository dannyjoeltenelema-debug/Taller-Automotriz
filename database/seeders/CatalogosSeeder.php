<?php

namespace Database\Seeders;

use App\Models\ChecklistItem;
use App\Models\EmpresaColaboradora;
use App\Models\Marca;
use App\Models\Servicio;
use Illuminate\Database\Seeder;

class CatalogosSeeder extends Seeder
{
    public function run(): void
    {
        $marcas = [
            'Chevrolet' => ['Sail', 'Aveo', 'Spark GT', 'D-Max', 'Tracker'],
            'Kia' => ['Sportage', 'Rio', 'Picanto', 'Soluto'],
            'Toyota' => ['Hilux', 'Fortuner', 'Corolla', 'Yaris'],
            'Hyundai' => ['Accent', 'Tucson', 'Creta'],
            'Suzuki' => ['Vitara', 'Swift', 'Grand Vitara'],
            'Nissan' => ['Frontier', 'Sentra', 'Kicks'],
            'Mazda' => ['BT-50', 'CX-5', 'Mazda 3'],
            'Great Wall' => ['Wingle 7', 'Haval H6'],
            'Renault' => ['Duster', 'Logan'],
        ];
        foreach ($marcas as $marca => $modelos) {
            $m = Marca::create(['nombre' => $marca]);
            foreach ($modelos as $modelo) {
                $m->modelos()->create(['nombre' => $modelo]);
            }
        }

        foreach ([
            ['Cambio de aceite y filtro', 'Aceite de motor + filtro de aceite (mano de obra)', 35.00],
            ['Alineación y balanceo', 'Alineación computarizada y balanceo de 4 ruedas', 28.00],
            ['Mantenimiento de frenos', 'Limpieza y regulación de frenos delanteros y posteriores', 45.00],
            ['ABC de motor', 'Limpieza de inyectores, cambio de bujías y filtros', 85.00],
            ['Diagnóstico por escáner', 'Lectura de códigos de falla OBD2', 20.00],
            ['Cambio de pastillas de freno', 'Mano de obra por eje', 25.00],
            ['Mantenimiento preventivo 5.000 km', 'Revisión de 21 puntos', 60.00],
            ['Revisión de suspensión', 'Inspección de amortiguadores, rótulas y terminales', 18.00],
        ] as [$n, $d, $p]) {
            Servicio::create(['nombre' => $n, 'descripcion' => $d, 'precio_referencial' => $p]);
        }

        foreach (['Luces delanteras y posteriores', 'Plumas limpiaparabrisas', 'Llanta de emergencia', 'Gata y llave de ruedas',
            'Radio / pantalla', 'Golpes o rayones visibles', 'Documentos (matrícula)', 'Tapacubos completos'] as $i => $n) {
            ChecklistItem::create(['nombre' => $n, 'orden' => $i + 1]);
        }

        EmpresaColaboradora::create([
            'nombre' => 'Corporación Petroandina S.A.', 'ruc' => '1790012345001', 'porcentaje_descuento' => 10,
            'contacto' => 'Ing. Patricio Erazo', 'telefono' => '022987654',
        ]);
        EmpresaColaboradora::create([
            'nombre' => 'Cooperativa de Taxis Pichincha Unidos', 'ruc' => '1791122334001', 'porcentaje_descuento' => 15,
            'contacto' => 'Sr. Marco Yánez', 'telefono' => '0987001122',
        ]);
    }
}
