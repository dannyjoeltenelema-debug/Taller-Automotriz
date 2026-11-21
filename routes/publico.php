<?php

use App\Livewire\Publico\Inicio;
use App\Livewire\Publico\VistaVehiculo;
use Illuminate\Support\Facades\Route;

// Portal público (sin login). throttle evita el barrido de placas.
// Las acciones Livewire (consultar, agendar) además aplican RateLimiter propio.
Route::middleware('throttle:'.config('taller.portal.throttle_por_minuto').',1')->group(function () {
    Route::get('/', Inicio::class)->name('home');
    Route::get('q/{token}', VistaVehiculo::class)
        ->where('token', '[0-9a-fA-F\-]{36}')
        ->name('publico.vehiculo');
});
