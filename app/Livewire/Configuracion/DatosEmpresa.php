<?php

namespace App\Livewire\Configuracion;

use App\Models\Empresa;
use App\Rules\CedulaRucEcuador;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Datos del taller (solo superadmin). */
#[Layout('components.layouts.panel')]
#[Title('Datos del taller')]
class DatosEmpresa extends Component
{
    public array $form = [];

    public string $aviso = '';

    public function mount(): void
    {
        $e = Empresa::actual() ?? new Empresa(['iva_porcentaje' => 15]);
        $this->form = [
            'razon_social' => (string) $e->razon_social,
            'ruc' => (string) $e->ruc,
            'iva_porcentaje' => (string) (float) $e->iva_porcentaje,
            'telefono' => (string) $e->telefono,
            'email' => (string) $e->email,
            'direccion' => (string) $e->direccion,
        ];
    }

    public function guardar(): void
    {
        $this->validate([
            'form.razon_social' => ['required', 'string', 'max:150'],
            'form.ruc' => ['required', 'digits:13', new CedulaRucEcuador('ruc')],
            'form.iva_porcentaje' => ['required', 'numeric', 'min:0', 'max:100'],
            'form.telefono' => ['nullable', 'regex:/^\+?\d{9,13}$/'],
            'form.email' => ['nullable', 'email'],
            'form.direccion' => ['nullable', 'string', 'max:255'],
        ], [], ['form.razon_social' => 'razón social', 'form.ruc' => 'RUC', 'form.iva_porcentaje' => 'IVA', 'form.telefono' => 'teléfono']);

        $e = Empresa::first() ?? new Empresa;
        $e->fill(array_map(fn ($v) => $v === '' ? null : $v, $this->form))->save();
        $this->aviso = 'Datos del taller actualizados.';
    }

    public function render()
    {
        return view('livewire.configuracion.datos-empresa');
    }
}
