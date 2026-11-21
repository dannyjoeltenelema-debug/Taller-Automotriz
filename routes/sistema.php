<?php

use App\Livewire\Sistema\Auditoria;
use App\Livewire\Sistema\Eliminados;
use App\Livewire\Sistema\Estado;
use App\Livewire\Sistema\Logs;
use App\Livewire\Sistema\Sesiones;
use App\Livewire\Sistema\Usuarios;
use Illuminate\Support\Facades\Route;

// SISTEMA: exclusivo del superadmin. El admin recibe 403 aunque escriba la URL.
Route::middleware(['auth', 'role:superadmin'])->prefix('panel/sistema')->name('sistema.')->group(function () {
    Route::redirect('/', '/panel/sistema/auditoria')->name('index');
    Route::get('auditoria', Auditoria::class)->name('auditoria');
    Route::get('usuarios', Usuarios::class)->name('usuarios');
    Route::get('sesiones', Sesiones::class)->name('sesiones');
    Route::get('eliminados', Eliminados::class)->name('eliminados');
    Route::get('estado', Estado::class)->name('estado');
    Route::get('logs', Logs::class)->name('logs');
});
