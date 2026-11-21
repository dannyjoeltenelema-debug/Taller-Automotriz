<?php

namespace App\Listeners;

use App\Models\Auditoria;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

/**
 * Login, logout y login fallido en la auditoría.
 * En el fallido se guarda SOLO la cédula intentada (nunca la contraseña) y la IP.
 */
class AuditarAcceso
{
    public function login(Login $e): void
    {
        Auditoria::registrar('login', $e->user instanceof User ? $e->user : null, null, null, $e->user->getAuthIdentifier());
    }

    public function logout(Logout $e): void
    {
        if ($e->user) {
            Auditoria::registrar('logout', $e->user instanceof User ? $e->user : null, null, null, $e->user->getAuthIdentifier());
        }
    }

    public function fallido(Failed $e): void
    {
        $cedula = mb_substr((string) ($e->credentials['cedula'] ?? $e->credentials['email'] ?? ''), 0, 20);

        Auditoria::registrar('login_fallido', null, null, ['cedula' => $cedula], $e->user?->getAuthIdentifier());
    }
}
