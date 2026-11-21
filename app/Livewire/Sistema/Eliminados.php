<?php

namespace App\Livewire\Sistema;

use App\Models\EmpresaColaboradora;
use App\Services\GestorConvenios;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Registros eliminados con soft delete y su restauración (solo superadmin).
 * Hoy: empresas colaboradoras. Para sumar otro tipo, agregarlo en render() con su restaurar.
 */
#[Layout('components.layouts.panel')]
#[Title('Registros eliminados')]
class Eliminados extends Component
{
    use SoloSuperadmin, WithPagination;

    public ?int $restaurandoId = null;

    public function confirmarRestaurar(int $id): void
    {
        $this->restaurandoId = $id;
    }

    public function restaurar(GestorConvenios $gestor): void
    {
        $convenio = EmpresaColaboradora::onlyTrashed()->findOrFail($this->restaurandoId);
        $gestor->restaurar($convenio);
        $this->restaurandoId = null;

        $this->dispatch('toast', mensaje: "Convenio «{$convenio->nombre}» restaurado. Vuelva a vincular sus clientes desde Clientes si corresponde.");
    }

    public function render()
    {
        return view('livewire.sistema.eliminados', [
            'convenios' => EmpresaColaboradora::onlyTrashed()
                ->select(['id', 'nombre', 'ruc', 'porcentaje_descuento', 'deleted_at'])
                ->latest('deleted_at')
                ->paginate(config('taller.paginacion.convenios')),
            'porRestaurar' => $this->restaurandoId
                ? EmpresaColaboradora::onlyTrashed()->find($this->restaurandoId, ['id', 'nombre'])
                : null,
        ]);
    }
}
