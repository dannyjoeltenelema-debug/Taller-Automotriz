<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Si el superadmin bloquea a un usuario que tiene la sesión abierta, en su siguiente
 * petición se cierra la sesión. El usuario ya viene cargado por el guard: no hay consulta extra.
 */
class BloquearUsuarioInhabilitado
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->bloqueado_at) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('status', 'Su acceso al sistema está bloqueado. Consulte con el administrador.');
        }

        return $next($request);
    }
}
