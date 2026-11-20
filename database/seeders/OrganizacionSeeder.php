<?php

namespace Database\Seeders;

use App\Models\Empleado;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class OrganizacionSeeder extends Seeder
{
    public function run(): void
    {
        $empresa = Empresa::create([
            'razon_social' => 'Mecánica Automotriz Andina S.A.',
            'ruc' => '1791357924001',
            'iva_porcentaje' => 15,
            'telefono' => '0998765432',
            'email' => 'contacto@mecanicaandina.ec',
            'direccion' => 'Av. 10 de Agosto N45-120 y Río Palora, Quito',
        ]);

        $norte = $empresa->sedes()->create([
            'nombre' => 'Quito Norte', 'codigo' => 'QN', 'direccion' => 'Av. 10 de Agosto N45-120, Quito',
            'telefono' => '022456789', 'horario' => 'Lun–Vie 08:00–17:00, Sáb 08:00–13:00',
        ]);
        $sur = $empresa->sedes()->create([
            'nombre' => 'Quito Sur', 'codigo' => 'QS', 'direccion' => 'Av. Maldonado S12-45, Quito',
            'telefono' => '022654321', 'horario' => 'Lun–Vie 08:00–17:00',
        ]);

        // Usuarios con empleado vinculado
        $super = User::create([
            'name' => 'Diego Salazar', 'email' => 'superadmin@taller.ec',
            'password' => Hash::make('password'), 'email_verified_at' => now(),
        ]);
        $super->assignRole('superadmin');
        Empleado::create([
            'sede_id' => $norte->id, 'user_id' => $super->id, 'nombres' => 'Diego', 'apellidos' => 'Salazar Mena',
            'cedula' => '1712345675', 'telefono' => '0991112233', 'cargo' => 'admin',
        ]);

        $admin = User::create([
            'name' => 'María Fernanda López', 'email' => 'admin@taller.ec',
            'password' => Hash::make('password'), 'email_verified_at' => now(),
        ]);
        $admin->assignRole('admin');
        Empleado::create([
            'sede_id' => $norte->id, 'user_id' => $admin->id, 'nombres' => 'María Fernanda', 'apellidos' => 'López Cevallos',
            'cedula' => '1709876542', 'telefono' => '0992223344', 'cargo' => 'asesor',
        ]);

        // Técnicos SIN usuario
        foreach ([
            ['Carlos', 'Guamán Quishpe', '1723456784', 'Motor y lubricación', $norte->id],
            ['Luis', 'Pilataxi Tipán', '1711112225', 'Frenos y suspensión', $norte->id],
            ['Jorge', 'Chicaiza Toapanta', '1804567897', 'Electricidad automotriz', $sur->id],
        ] as [$n, $a, $c, $esp, $sede]) {
            Empleado::create([
                'sede_id' => $sede, 'nombres' => $n, 'apellidos' => $a, 'cedula' => $c,
                'telefono' => '09'.substr($c, 2, 8), 'cargo' => 'tecnico', 'especialidad' => $esp,
            ]);
        }
    }
}
