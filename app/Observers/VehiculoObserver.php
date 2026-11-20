<?php

namespace App\Observers;

use App\Models\Vehiculo;
use Illuminate\Support\Str;

class VehiculoObserver
{
    public function creating(Vehiculo $vehiculo): void
    {
        $vehiculo->qr_token ??= (string) Str::uuid();
    }
}
