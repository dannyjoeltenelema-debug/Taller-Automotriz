<?php

namespace App\Livewire\Empleados;

use App\Models\Empleado;
use App\Models\Sede;
use App\Models\User;
use App\Rules\CedulaRucEcuador;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Técnicos y mecánicos se registran SIN usuario (no tienen login).
 * Para cargos admin/asesor se puede crear un usuario vinculado cuando haga falta.
 */
#[Layout('components.layouts.panel')]
#[Title('Empleados')]
class Listado extends Component
{
    public string $filtroCargo = '';

    public string $aviso = '';

    public bool $modal = false;

    public ?int $editandoId = null;

    public array $form = [];

    // Acceso al sistema (opcional)
    public bool $crearAcceso = false;

    public string $email = '';

    public string $password = '';

    public string $rol = 'admin';

    public function nuevo(): void
    {
        $this->resetValidation();
        $this->editandoId = null;
        $this->form = [
            'nombres' => '', 'apellidos' => '', 'cedula' => '', 'telefono' => '', 'cargo' => 'tecnico',
            'especialidad' => '', 'sede_id' => auth()->user()->empleado?->sede_id ?? Sede::value('id'), 'activo' => true,
        ];
        $this->reset(['crearAcceso', 'email', 'password']);
        $this->rol = 'admin';
        $this->modal = true;
    }

    public function editar(int $id): void
    {
        $e = Empleado::findOrFail($id);
        $this->resetValidation();
        $this->editandoId = $e->id;
        $this->form = $e->only(['nombres', 'apellidos', 'cedula', 'telefono', 'cargo', 'especialidad', 'sede_id', 'activo']);
        $this->form['telefono'] = (string) $this->form['telefono'];
        $this->form['especialidad'] = (string) $this->form['especialidad'];
        $this->reset(['crearAcceso', 'email', 'password']);
        $this->modal = true;
    }

    public function guardar(): void
    {
        $empleadoActual = $this->editandoId ? Empleado::find($this->editandoId) : null;
        $puedeAcceso = in_array($this->form['cargo'], ['admin', 'asesor']) && ! $empleadoActual?->user_id;
        $rolesPermitidos = auth()->user()->hasRole('superadmin') ? ['admin', 'superadmin'] : ['admin'];

        $this->validate([
            'form.nombres' => ['required', 'string', 'max:100'],
            'form.apellidos' => ['required', 'string', 'max:100'],
            'form.cedula' => ['required', 'digits:10', new CedulaRucEcuador('cedula'), Rule::unique('empleados', 'cedula')->ignore($this->editandoId)],
            'form.telefono' => ['nullable', 'regex:/^\+?\d{9,13}$/'],
            'form.cargo' => ['required', Rule::in(array_keys(Empleado::CARGOS))],
            'form.especialidad' => ['nullable', 'string', 'max:100'],
            'form.sede_id' => ['required', 'exists:sedes,id'],
            'form.activo' => ['boolean'],
            'email' => [Rule::requiredIf($this->crearAcceso && $puedeAcceso), 'nullable', 'email', 'max:120', 'unique:users,email'],
            'password' => [Rule::requiredIf($this->crearAcceso && $puedeAcceso), 'nullable', Password::min(8)],
            'rol' => [Rule::in($rolesPermitidos)],
        ], [], [
            'form.nombres' => 'nombres', 'form.apellidos' => 'apellidos', 'form.cedula' => 'cédula',
            'form.telefono' => 'teléfono', 'form.cargo' => 'cargo', 'form.sede_id' => 'sede', 'email' => 'correo', 'password' => 'contraseña',
        ]);

        DB::transaction(function () use ($puedeAcceso) {
            $datos = $this->form;
            $datos['telefono'] = $datos['telefono'] ?: null;
            $datos['especialidad'] = $datos['especialidad'] ?: null;

            $empleado = Empleado::updateOrCreate(['id' => $this->editandoId], $datos);

            if ($this->crearAcceso && $puedeAcceso) {
                $user = User::create([
                    'name' => $empleado->nombre_completo,
                    'email' => $this->email,
                    'password' => Hash::make($this->password),
                    'email_verified_at' => now(),
                ]);
                $user->assignRole($this->rol);
                $empleado->update(['user_id' => $user->id]);
            }

            // Si se desactiva un empleado con login, se le quitan los roles (pierde acceso al panel).
            if (! $empleado->activo && $empleado->user && $empleado->user_id !== auth()->id()) {
                $empleado->user->syncRoles([]);
            }
        });

        $this->modal = false;
        $this->aviso = 'Empleado guardado.';
    }

    public function render()
    {
        return view('livewire.empleados.listado', [
            'empleados' => Empleado::with(['sede', 'user.roles'])
                ->when($this->filtroCargo, fn ($q) => $q->where('cargo', $this->filtroCargo))
                ->orderByDesc('activo')->orderBy('cargo')->orderBy('apellidos')->get(),
            'sedes' => Sede::orderBy('nombre')->get(),
            'empleadoEditando' => $this->editandoId ? Empleado::with('user')->find($this->editandoId) : null,
        ]);
    }
}
