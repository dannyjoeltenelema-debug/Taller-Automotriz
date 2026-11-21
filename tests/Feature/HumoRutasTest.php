<?php

namespace Tests\Feature;

use App\Models\OrdenTrabajo;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Route as Ruta;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middleware\RoleMiddleware;
use Tests\TestCase;

/**
 * Test de humo PERMANENTE: recorre TODAS las rutas GET registradas con cuatro perfiles
 * (invitado, usuario sin rol, admin, superadmin), rellenando parámetros con datos de los seeders.
 *
 * Tabla esperada (derivada del middleware de cada ruta):
 *  - Portal público: 200 para todos.
 *  - Panel: invitado → redirect a /login; sin rol → 403; admin y superadmin → 200 (o 302 si la ruta
 *    es un redirect), salvo lo exclusivo de superadmin (Datos del taller, Sistema) → 403 para admin.
 *  - Pantallas solo de invitado (login, recuperar contraseña): invitado 200; con sesión → redirect.
 * Cualquier 500, o un 403/404 fuera de esta tabla, es un fallo.
 */
class HumoRutasTest extends TestCase
{
    use RefreshDatabase;

    /** Rutas de paquetes (assets) y las que requieren una URL firmada o un token de correo. */
    private const OMITIR = ['_debugbar', 'flux/', 'livewire-', 'storage/', 'verify-email/{id}/{hash}'];

    public function test_todas_las_rutas_get_responden_lo_esperado_por_perfil(): void
    {
        $this->seed();
        $this->withoutMiddleware(ThrottleRequests::class);

        $perfiles = [
            'invitado' => null,
            'sin_rol' => User::factory()->create(),
            'admin' => User::where('cedula', '1710000009')->first(),
            'superadmin' => User::where('cedula', '1729057412')->first(),
        ];
        $params = [
            'orden' => OrdenTrabajo::firstOrFail()->id,
            'vehiculo' => Vehiculo::firstOrFail()->id,
            'token' => Vehiculo::firstOrFail()->qr_token,
        ];

        $fallos = [];
        $revisadas = 0;
        foreach (Route::getRoutes() as $ruta) {
            /** @var Ruta $ruta */
            if (! in_array('GET', $ruta->methods(), true) || $this->omitir($ruta->uri())) {
                continue;
            }
            $url = $this->url($ruta, $params);

            foreach ($perfiles as $perfil => $user) {
                $this->app['auth']->forgetGuards();
                if ($user) {
                    $this->actingAs($user);
                }
                $status = $this->get($url)->getStatusCode();
                $esperado = $this->esperado($ruta, $user);
                $revisadas++;

                if ($status >= 500 || ! in_array($status, (array) $esperado, true)) {
                    $fallos[] = sprintf('%-40s %-10s → %d (esperado %s)', $url, $perfil, $status, implode('/', (array) $esperado));
                }
            }
        }

        $this->assertGreaterThan(100, $revisadas);
        $this->assertSame([], $fallos, "Respuestas inesperadas:\n".implode("\n", $fallos));
    }

    private function omitir(string $uri): bool
    {
        foreach (self::OMITIR as $o) {
            if (str_starts_with($uri, $o) || $uri === $o) {
                return true;
            }
        }

        return false;
    }

    private function url(Ruta $ruta, array $params): string
    {
        $uri = preg_replace_callback('/\{(\w+)\??\}/', fn ($m) => $params[$m[1]] ?? 'token-de-prueba', $ruta->uri());

        return '/'.ltrim($uri, '/');
    }

    /** @return int|list<int> */
    private function esperado(Ruta $ruta, ?User $user): int|array
    {
        $mw = $ruta->gatherMiddleware();
        $tiene = fn (string $clase) => (bool) array_filter($mw, fn ($m) => $m === $clase || str_starts_with($m, $clase.':')
            || $m === 'auth' && $clase === Authenticate::class || $m === 'guest' && $clase === RedirectIfAuthenticated::class
            || str_starts_with($m, 'role:') && $clase === RoleMiddleware::class);
        $esRedirect = $ruta->getActionName() === '\Illuminate\Routing\RedirectController' || str_contains($ruta->getActionName(), 'RedirectController')
            || $ruta->uri() === 'dashboard';

        if ($tiene(RedirectIfAuthenticated::class)) {
            return $user ? 302 : 200;
        }
        if (! $tiene(Authenticate::class)) {
            return 200; // portal público y health check
        }
        if (! $user) {
            return 302; // a /login
        }

        // Todas las restricciones de rol de la ruta (puede haber dos: superadmin|admin y luego superadmin).
        foreach ($mw as $m) {
            $roles = null;
            if (str_starts_with($m, RoleMiddleware::class.':')) {
                $roles = substr($m, strlen(RoleMiddleware::class) + 1);
            } elseif (str_starts_with($m, 'role:')) {
                $roles = substr($m, 5);
            }
            if ($roles !== null && ! $user->hasAnyRole(explode('|', explode(',', $roles)[0]))) {
                return 403;
            }
        }

        if ($esRedirect) {
            return 302;
        }

        return 200;
    }
}
