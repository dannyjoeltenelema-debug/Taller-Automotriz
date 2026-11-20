<?php

use App\Livewire\Configuracion\Catalogos;
use App\Livewire\Configuracion\DatosEmpresa;
use App\Livewire\Empleados\Listado;
use Illuminate\Support\Facades\Route;

// Administración: empleados, catálogos (servicios, checklist, marcas) y datos del taller.
Route::middleware(['auth', 'role:superadmin|admin'])->prefix('panel')->group(function () {
    Route::get('empleados', Listado::class)->name('empleados.index');
    Route::get('catalogos', Catalogos::class)->name('catalogos.index');

    Route::get('configuracion', DatosEmpresa::class)
        ->middleware('role:superadmin')
        ->name('configuracion.empresa');
});
