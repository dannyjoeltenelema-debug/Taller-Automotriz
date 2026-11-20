<?php

use App\Livewire\Citas\Listado;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:superadmin|admin'])->prefix('panel')->group(function () {
    Route::get('citas', Listado::class)->name('citas.index');
});
