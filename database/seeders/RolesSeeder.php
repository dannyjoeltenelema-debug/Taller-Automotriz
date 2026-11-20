<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Roles v1: superadmin (todo) y admin (opera todo). Técnicos no tienen login.
 * Para sumar roles después (p. ej. 'bodeguero' para Inventario) basta con
 * Role::create(...) + permisos: no requiere migración.
 */
class RolesSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['superadmin', 'admin'] as $rol) {
            Role::findOrCreate($rol, 'web');
        }
    }
}
