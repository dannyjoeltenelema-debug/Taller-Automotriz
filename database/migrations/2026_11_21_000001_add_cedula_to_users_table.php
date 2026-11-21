<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El login pasa a ser por cédula (campo "Usuario"); el correo queda opcional
 * y solo sirve para recuperar la contraseña.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Nullable a nivel de BD para no romper filas ya existentes; la app la exige al crear usuarios.
            $table->string('cedula', 13)->nullable()->unique()->after('name');
            $table->string('email')->nullable()->change();
        });

        // Usuarios ya vinculados a un empleado heredan su cédula.
        DB::table('users')
            ->whereNull('cedula')
            ->whereExists(fn ($q) => $q->from('empleados')->whereColumn('empleados.user_id', 'users.id'))
            ->update(['cedula' => DB::raw('(select empleados.cedula from empleados where empleados.user_id = users.id limit 1)')]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['cedula']);
            $table->dropColumn('cedula');
            $table->string('email')->nullable(false)->change();
        });
    }
};
