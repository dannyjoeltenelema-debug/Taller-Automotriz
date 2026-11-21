<?php

namespace App\Livewire\Sistema;

/**
 * Defensa en profundidad: además del middleware de la ruta (role:superadmin), cada petición
 * Livewire del componente (acciones, carga diferida) vuelve a exigir el rol.
 */
trait SoloSuperadmin
{
    public function bootSoloSuperadmin(): void
    {
        abort_unless(auth()->user()?->hasRole('superadmin'), 403);
    }
}
