<?php

namespace App\Livewire\Sistema;

use App\Models\Auditoria;
use App\Models\Empleado;
use App\Models\User;
use App\Rules\CedulaRucEcuador;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

/**
 * Usuarios del panel: crear, editar, rol Spatie, vínculo con empleado, restablecer contraseña
 * y bloquear/desbloquear. Reglas: un superadmin no se bloquea ni se quita el rol a sí mismo,
 * y siempre queda al menos un superadmin activo (no bloqueado).
 */
#[Layout('components.layouts.panel')]
#[Title('Usuarios y roles')]
class Usuarios extends Component
{
    use SoloSuperadmin, WithPagination;

    #[Url(as: 'q', except: '')]
    public string $buscar = '';

    public bool $modal = false;

    public ?int $editandoId = null;

    public array $form = [];

    /** Contraseña temporal recién generada (se muestra UNA vez). */
    public ?string $claveTemporal = null;

    public ?string $claveDe = null;

    /** Confirmación de bloqueo / restablecer. */
    public ?int $confirmarId = null;

    public string $confirmarAccion = '';

    public function updatedBuscar(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function roles()
    {
        return Role::orderBy('name')->pluck('name');
    }

    /** Empleados que se pueden vincular: sin usuario o ya vinculados a este. */
    #[Computed]
    public function empleados()
    {
        return Empleado::query()
            ->where(fn ($q) => $q->whereNull('user_id')->when($this->editandoId, fn ($w) => $w->orWhere('user_id', $this->editandoId)))
            ->orderBy('apellidos')
            ->get(['id', 'nombres', 'apellidos', 'cedula', 'cargo']);
    }

    public function nuevo(): void
    {
        $this->resetValidation();
        $this->editandoId = null;
        $this->form = ['name' => '', 'cedula' => '', 'email' => '', 'rol' => 'admin', 'empleado_id' => '', 'password' => ''];
        $this->modal = true;
    }

    public function editar(int $id): void
    {
        $u = User::with(['roles:id,name', 'empleado:id,user_id'])->findOrFail($id);
        $this->resetValidation();
        $this->editandoId = $u->id;
        $this->form = [
            'name' => $u->name, 'cedula' => (string) $u->cedula, 'email' => (string) $u->email,
            'rol' => $u->roles->first()?->name ?? '', 'empleado_id' => (string) ($u->empleado?->id ?? ''), 'password' => '',
        ];
        $this->modal = true;
    }

    public function guardar(): void
    {
        $this->form['cedula'] = trim($this->form['cedula']);
        $datos = $this->validate([
            'form.name' => ['required', 'string', 'max:120'],
            'form.cedula' => ['required', 'digits:10', new CedulaRucEcuador('cedula'), Rule::unique('users', 'cedula')->ignore($this->editandoId)],
            'form.email' => ['nullable', 'email', 'max:120', Rule::unique('users', 'email')->ignore($this->editandoId)],
            'form.rol' => ['required', Rule::in($this->roles->all())],
            'form.empleado_id' => ['nullable', Rule::exists('empleados', 'id')->where(fn ($q) => $q->whereNull('user_id')->when($this->editandoId, fn ($w) => $w->orWhere('user_id', $this->editandoId)))],
            'form.password' => [$this->editandoId ? 'nullable' : 'required', 'string', 'min:8', 'max:100'],
        ], [], [
            'form.name' => 'nombre', 'form.cedula' => 'cédula', 'form.email' => 'correo', 'form.rol' => 'rol',
            'form.empleado_id' => 'empleado', 'form.password' => 'contraseña',
        ])['form'];

        $user = $this->editandoId ? User::with('roles:id,name')->findOrFail($this->editandoId) : null;

        // Un superadmin no se quita el rol a sí mismo; y siempre queda al menos un superadmin activo.
        if ($user && $user->hasRole('superadmin') && $datos['rol'] !== 'superadmin') {
            if ($user->id === auth()->id()) {
                $this->addError('form.rol', 'No puede quitarse a sí mismo el rol de superadmin.');

                return;
            }
            if (! $this->quedaOtroSuperadminActivo($user->id)) {
                $this->addError('form.rol', 'Debe quedar al menos un superadmin activo.');

                return;
            }
        }

        DB::transaction(function () use ($user, $datos) {
            $campos = ['name' => trim($datos['name']), 'cedula' => $datos['cedula'], 'email' => $datos['email'] ?: null];
            if (! empty($datos['password'])) {
                $campos['password'] = $datos['password']; // cast "hashed"
            }

            $user = $user ? tap($user)->update($campos) : User::create($campos);
            $user->syncRoles([$datos['rol']]);

            // Vínculo con empleado (uno a uno): se suelta el anterior y se asigna el nuevo.
            Empleado::where('user_id', $user->id)->when($datos['empleado_id'], fn ($q, $id) => $q->whereKeyNot($id))
                ->get()->each->update(['user_id' => null]);
            if ($datos['empleado_id']) {
                Empleado::whereKey($datos['empleado_id'])->first()?->update(['user_id' => $user->id]);
            }
        });

        $this->modal = false;
        $this->dispatch('toast', mensaje: 'Usuario guardado.');
    }

    // ───────────── Bloquear / restablecer (con modal de confirmación) ─────────────

    public function pedirConfirmacion(int $id, string $accion): void
    {
        abort_unless(in_array($accion, ['bloquear', 'desbloquear', 'restablecer'], true), 422);
        $this->confirmarId = $id;
        $this->confirmarAccion = $accion;
    }

    public function cancelarConfirmacion(): void
    {
        $this->reset(['confirmarId', 'confirmarAccion']);
    }

    public function confirmar(): void
    {
        $user = User::with('roles:id,name')->findOrFail($this->confirmarId);
        $accion = $this->confirmarAccion;
        $this->cancelarConfirmacion();

        match ($accion) {
            'bloquear' => $this->bloquear($user),
            'desbloquear' => $this->desbloquear($user),
            'restablecer' => $this->restablecer($user),
        };
    }

    private function bloquear(User $user): void
    {
        if ($user->id === auth()->id()) {
            $this->dispatch('toast', tipo: 'error', mensaje: 'No puede bloquear su propio usuario.');

            return;
        }
        if ($user->hasRole('superadmin') && ! $this->quedaOtroSuperadminActivo($user->id)) {
            $this->dispatch('toast', tipo: 'error', mensaje: 'Debe quedar al menos un superadmin activo.');

            return;
        }

        DB::transaction(function () use ($user) {
            // remember_token nuevo: invalida el "Recordarme" de sus navegadores.
            $user->forceFill(['bloqueado_at' => now(), 'remember_token' => Str::random(60)])->saveQuietly();
            Auditoria::registrar('bloqueado', $user);
            if (config('session.driver') === 'database') {
                DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
            }
        });

        $this->dispatch('toast', mensaje: "Acceso de {$user->name} bloqueado. Sus sesiones abiertas se cerraron.");
    }

    private function desbloquear(User $user): void
    {
        $user->forceFill(['bloqueado_at' => null])->saveQuietly();
        Auditoria::registrar('desbloqueado', $user);
        $this->dispatch('toast', mensaje: "Acceso de {$user->name} desbloqueado.");
    }

    private function restablecer(User $user): void
    {
        $clave = Str::password(12, symbols: false);
        $user->forceFill(['password' => $clave, 'remember_token' => Str::random(60)])->saveQuietly();
        Auditoria::registrar('password_restablecida', $user);

        $this->claveTemporal = $clave;
        $this->claveDe = $user->name;
    }

    private function quedaOtroSuperadminActivo(int $exceptoId): bool
    {
        return User::role('superadmin')->whereNull('bloqueado_at')->whereKeyNot($exceptoId)->exists();
    }

    public function render()
    {
        $t = trim($this->buscar);

        return view('livewire.sistema.usuarios', [
            'usuarios' => User::query()
                ->select(['id', 'name', 'cedula', 'email', 'bloqueado_at', 'created_at'])
                ->with(['roles:id,name', 'empleado:id,user_id,nombres,apellidos,cargo'])
                ->when($t !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$t}%")->orWhere('cedula', 'like', "{$t}%")))
                ->orderBy('name')
                ->paginate(config('taller.paginacion.usuarios')),
            'confirmando' => $this->confirmarId ? User::find($this->confirmarId, ['id', 'name']) : null,
        ]);
    }
}
