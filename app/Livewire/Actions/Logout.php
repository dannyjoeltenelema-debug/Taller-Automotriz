<?php

namespace App\Livewire\Actions;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class Logout
{
    /**
     * Log the current user out of the application.
     */
    public function __invoke()
    {
        Auth::guard('web')->logout();

        Session::invalidate();
        Session::regenerateToken();

        // Desde el panel se llama con fetch (Alpine) y luego navega con wire:navigate: no hace falta redirigir.
        if (request()->expectsJson()) {
            return response()->noContent();
        }

        return redirect('/');
    }
}
