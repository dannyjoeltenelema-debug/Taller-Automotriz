<?php

namespace App\Providers;

use App\Models\OrdenTrabajo;
use App\Models\Vehiculo;
use App\Observers\OrdenTrabajoObserver;
use App\Observers\VehiculoObserver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Vehiculo::observe(VehiculoObserver::class);
        OrdenTrabajo::observe(OrdenTrabajoObserver::class);

        // superadmin tiene acceso total a cualquier permiso/gate.
        Gate::before(fn ($user) => $user->hasRole('superadmin') ? true : null);
    }
}
