<?php

use App\Http\Controllers\OrdenPdfController;
use App\Livewire\Ordenes\Formulario;
use App\Livewire\Ordenes\Listado;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:superadmin|admin'])->prefix('panel')->group(function () {
    // Home del panel
    Route::redirect('/', '/panel/ordenes');
    Route::redirect('/dashboard', '/panel/ordenes')->name('dashboard');

    Route::get('ordenes', Listado::class)->name('ordenes.index');
    Route::get('ordenes/nueva', Formulario::class)->name('ordenes.create');
    Route::get('ordenes/{orden}/editar', Formulario::class)->name('ordenes.edit');
    Route::get('ordenes/{orden}/pdf', OrdenPdfController::class)->name('ordenes.pdf');
});

// Compatibilidad con el starter kit (redirige tras login)
Route::middleware(['auth'])->get('dashboard', fn () => redirect()->route('ordenes.index'));
