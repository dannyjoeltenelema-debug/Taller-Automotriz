<?php

namespace App\Livewire\Sistema;

use App\Models\Auditoria;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Sesiones activas (solo con SESSION_DRIVER=database; con otro driver se muestra un aviso
 * y no se cambia nada). Cerrar una sesión borra su fila: el usuario vuelve al login.
 */
#[Layout('components.layouts.panel')]
#[Title('Sesiones activas')]
class Sesiones extends Component
{
    use SoloSuperadmin, WithPagination;

    public ?string $cerrandoId = null;

    private function disponible(): bool
    {
        return config('session.driver') === 'database';
    }

    private function tabla(): string
    {
        return config('session.table', 'sessions');
    }

    public function confirmarCierre(string $id): void
    {
        $this->cerrandoId = $id;
    }

    public function cerrar(): void
    {
        abort_unless($this->disponible(), 404);
        $id = $this->cerrandoId;
        $this->cerrandoId = null;

        if ($id === session()->getId()) {
            $this->dispatch('toast', tipo: 'error', mensaje: 'Esa es su sesión actual: use "Cerrar sesión".');

            return;
        }

        $sesion = DB::table($this->tabla())->where('id', $id)->first(['user_id', 'ip_address']);
        if (! $sesion) {
            return;
        }
        DB::table($this->tabla())->where('id', $id)->delete();
        Auditoria::registrar('sesion_cerrada', $sesion->user_id ? (new User)->forceFill(['id' => $sesion->user_id]) : null, null, ['ip_sesion' => $sesion->ip_address]);

        $this->dispatch('toast', mensaje: 'Sesión cerrada.');
    }

    public function render()
    {
        $sesiones = null;
        if ($this->disponible()) {
            $minimo = now()->subMinutes((int) config('session.lifetime'))->getTimestamp();
            $sesiones = DB::table($this->tabla().' as s')
                ->leftJoin('users as u', 'u.id', '=', 's.user_id')
                ->where('s.last_activity', '>=', $minimo)
                ->orderByDesc('s.last_activity')
                ->select(['s.id', 's.user_id', 's.ip_address', 's.user_agent', 's.last_activity', 'u.name', 'u.cedula'])
                ->paginate(config('taller.paginacion.usuarios'));
            $sesiones->getCollection()->transform(function ($s) {
                $s->ultima = Carbon::createFromTimestamp($s->last_activity, config('app.timezone'));

                return $s;
            });
        }

        return view('livewire.sistema.sesiones', [
            'disponible' => $this->disponible(),
            'sesiones' => $sesiones,
            'actual' => session()->getId(),
        ]);
    }
}
