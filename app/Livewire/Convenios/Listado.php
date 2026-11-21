<?php

namespace App\Livewire\Convenios;

use App\Models\EmpresaColaboradora;
use App\Rules\CedulaRucEcuador;
use App\Services\GestorConvenios;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.panel')]
#[Title('Empresas colaboradoras')]
class Listado extends Component
{
    use WithPagination;

    public bool $modal = false;

    public ?int $editandoId = null;

    public string $nombre = '';

    public string $ruc = '';

    public $porcentaje_descuento = '';

    public string $contacto = '';

    public string $telefono = '';

    /** Convenio a eliminar (abre el modal de confirmación). */
    public ?int $eliminandoId = null;

    public function nuevo(): void
    {
        $this->reset(['editandoId', 'nombre', 'ruc', 'porcentaje_descuento', 'contacto', 'telefono']);
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
        $this->modal = true;
    }

    public function guardar(): void
    {
        $datos = $this->validate([
            'nombre' => ['required', 'string', 'max:150'],
            // El UNIQUE de BD incluye los eliminados: si el RUC es de uno eliminado, se restaura desde Sistema.
            'ruc' => ['nullable', 'digits:13', new CedulaRucEcuador('ruc'), Rule::unique('empresas_colaboradoras', 'ruc')->ignore($this->editandoId)],
            'porcentaje_descuento' => ['required', 'numeric', 'min:0', 'max:100'],
            'contacto' => ['nullable', 'string', 'max:120'],
            'telefono' => ['nullable', 'regex:/^\+?\d{9,13}$/'],
        ], [
            'ruc.unique' => 'Ya existe una empresa colaboradora con ese RUC (puede estar eliminada: el superadmin la restaura en Sistema > Registros eliminados).',
        ], ['porcentaje_descuento' => '% de descuento', 'telefono' => 'teléfono']);

        $datos['ruc'] = $datos['ruc'] ?: null;
        $nueva = ! $this->editandoId;
        $e = EmpresaColaboradora::updateOrCreate(['id' => $this->editandoId], $datos);

        $this->modal = false;
        $this->dispatch('toast', mensaje: 'Empresa colaboradora guardada.');
        if ($nueva) {
            $this->dispatch('fila-nueva', id: 'emp-'.$e->id);
        }
    }

    // ───────────── Eliminar (soft delete) ─────────────

    public function confirmarEliminar(int $id): void
    {
        $this->eliminandoId = $id; // el modal (render) carga nombre y nº de clientes
    }

    public function eliminar(GestorConvenios $gestor): void
    {
        $convenio = EmpresaColaboradora::findOrFail($this->eliminandoId);
        $n = $gestor->eliminar($convenio);
        $this->eliminandoId = null;

        $this->dispatch('toast', mensaje: "Convenio «{$convenio->nombre}» eliminado. {$n} ".($n === 1 ? 'cliente quedó' : 'clientes quedaron')." en 'No aplica'.");
    }

    public function render()
    {
        return view('livewire.convenios.listado', [
            'empresas' => EmpresaColaboradora::withCount('clientes')->orderBy('nombre')->paginate(config('taller.paginacion.convenios')),
            // Modal de eliminar: cuántos clientes quedarán en "No aplica".
            'porEliminar' => $this->eliminandoId
                ? EmpresaColaboradora::withCount('clientes')->findOrFail($this->eliminandoId, ['id', 'nombre'])
                : null,
        ]);
    }
}
