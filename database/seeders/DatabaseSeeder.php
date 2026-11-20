<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * No se siembran datos de Inventario: eso lo hace el módulo del otro programador.
     */
    public function run(): void
    {
        $this->call([
            RolesSeeder::class,
            OrganizacionSeeder::class,
            CatalogosSeeder::class,
            DemoOperacionSeeder::class,
        ]);
    }
}
