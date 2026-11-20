<?php

use App\Livewire\Clientes\Listado as ClientesListado;
use App\Livewire\Convenios\Listado as ConveniosListado;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:superadmin|admin'])->prefix('panel')->group(function () {
    Route::get('clientes', ClientesListado::class)->name('clientes.index');
    Route::get('empresas-colaboradoras', ConveniosListado::class)->name('convenios.index');
});
