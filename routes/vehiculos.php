<?php

use App\Http\Controllers\EtiquetaQrController;
use App\Livewire\Vehiculos\Ficha;
use App\Livewire\Vehiculos\Formulario;
use App\Livewire\Vehiculos\Listado;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:superadmin|admin'])->prefix('panel')->group(function () {
    Route::get('vehiculos', Listado::class)->name('vehiculos.index');
    Route::get('vehiculos/nuevo', Formulario::class)->name('vehiculos.create');
    Route::get('vehiculos/{vehiculo}', Ficha::class)->name('vehiculos.show');
    Route::get('vehiculos/{vehiculo}/editar', Formulario::class)->name('vehiculos.edit');
    Route::get('vehiculos/{vehiculo}/etiqueta', EtiquetaQrController::class)->name('vehiculos.etiqueta');
});
