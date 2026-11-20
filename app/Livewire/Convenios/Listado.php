<?php

namespace App\Livewire\Convenios;

use App\Models\EmpresaColaboradora;
use App\Rules\CedulaRucEcuador;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.panel')]
#[Title('Empresas colaboradoras')]
class Listado extends Component
{
    public bool $modal = false;

    public string $aviso = '';

    public ?int $editandoId = null;

    public string $nombre = '';

    public string $ruc = '';

    public $porcentaje_descuento = '';

    public string $contacto = '';

    public string $telefono = '';

    public bool $activo = true;

    public function nuevo(): void
    {
        $this->reset(['editandoId', 'nombre', 'ruc', 'porcentaje_descuento', 'contacto', 'telefono']);
        $this->activo = true;
        $this->resetValidation();
        $this->modal = true;
    }

    public function editar(int $id): void
    {
        $e = EmpresaColaboradora::findOrFail($id);
        $this->resetValidation();
        $this->editandoId = $e->id;
        $this->nombre = $e->nombre;
        $this->ruc = (string) $e->ruc;
        $this->porcentaje_descuento = (string) (float) $e->porcentaje_descuento;
        $this->contacto = (string) $e->contacto;
        $this->telefono = (string) $e->telefono;
        $this->activo = $e->activo;
        $this->modal = true;
    }

    public function guardar(): void
    {
        $datos = $this->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'ruc' => ['nullable', 'digits:13', new CedulaRucEcuador('ruc'), Rule::unique('empresas_colaboradoras', 'ruc')->ignore($this->editandoId)],
            'porcentaje_descuento' => ['required', 'numeric', 'min:0', 'max:100'],
            'contacto' => ['nullable', 'string', 'max:120'],
            'telefono' => ['nullable', 'regex:/^\+?\d{9,13}$/'],
            'activo' => ['boolean'],
        ], [], ['porcentaje_descuento' => '% de descuento', 'telefono' => 'teléfono']);

        $datos['ruc'] = $datos['ruc'] ?: null;
        EmpresaColaboradora::updateOrCreate(['id' => $this->editandoId], $datos);

        $this->modal = false;
        $this->aviso = 'Empresa colaboradora guardada.';
    }

    public function render()
    {
        return view('livewire.convenios.listado', [
            'empresas' => EmpresaColaboradora::withCount('clientes')->orderBy('nombre')->get(),
        ]);
    }
}
