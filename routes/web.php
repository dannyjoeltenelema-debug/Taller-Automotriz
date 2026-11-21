<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

// Ajustes de cuenta (starter kit, con Flux). Solo usuarios con un rol del panel.
Route::middleware(['auth', 'role:superadmin|admin'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
});

// Rutas divididas por módulo
require __DIR__.'/ordenes.php';
require __DIR__.'/clientes.php';
require __DIR__.'/vehiculos.php';
require __DIR__.'/citas.php';
require __DIR__.'/empleados.php';
require __DIR__.'/publico.php';
require __DIR__.'/sistema.php';  // solo superadmin
require __DIR__.'/inventario.php'; // vacío, del otro dev

require __DIR__.'/auth.php';
