<?php

namespace App\Providers;

use App\Models\OrdenTrabajo;
use App\Models\Vehiculo;
use App\Observers\OrdenTrabajoObserver;
use App\Observers\VehiculoObserver;
use App\Listeners\AuditarAcceso;
use App\Support\Catalogos;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
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

        // Auditoría de acceso (login, logout, login fallido).
        Event::listen(Login::class, [AuditarAcceso::class, 'login']);
        Event::listen(Logout::class, [AuditarAcceso::class, 'logout']);
        Event::listen(Failed::class, [AuditarAcceso::class, 'fallido']);

        // superadmin tiene acceso total a cualquier permiso/gate.
        Gate::before(fn ($user) => $user->hasRole('superadmin') ? true : null);
    }
}
