<?php

namespace App\Providers;

use App\Models\OrdenTrabajo;
use App\Models\Vehiculo;
use App\Observers\OrdenTrabajoObserver;
use App\Observers\VehiculoObserver;
use App\Support\Catalogos;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Memo de la caché de catálogos: una instancia por request/job (seguro en Octane y colas).
        $this->app->scoped(Catalogos::class);
    }

    public function boot(): void
    {
        Vehiculo::observe(VehiculoObserver::class);
        OrdenTrabajo::observe(OrdenTrabajoObserver::class);

        // superadmin tiene acceso total a cualquier permiso/gate.
        Gate::before(fn ($user) => $user->hasRole('superadmin') ? true : null);
    }
}
