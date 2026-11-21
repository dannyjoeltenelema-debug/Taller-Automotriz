<?php

namespace App\Livewire\Ordenes;

use App\Models\Cita;
use App\Models\OrdenTrabajo;
use App\Support\Placa;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.panel')]
#[Title('Órdenes de trabajo')]
class Listado extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $buscar = '';

    #[Url(except: 'activas')]
    public string $estado = 'activas';

    public function updatedBuscar(): void
    {
        $this->resetPage();
    }

    public function updatedEstado(): void
    {
        $this->resetPage();
    }

    /** Cambio de estado inline (el observer registra la traza en ot_estados). */
    public function cambiarEstado(int $id, string $estado): void
    {
        abort_unless(array_key_exists($estado, OrdenTrabajo::ESTADOS), 422);

        $orden = OrdenTrabajo::findOrFail($id);
        $orden->update(['estado' => $estado]);

        $this->dispatch('aviso', mensaje: "{$orden->codigo}: estado cambiado a ".OrdenTrabajo::ESTADOS[$estado].'.');
    }

    /**
     * Detecta el tipo de término:
     *  - OT-2026-…      → código
     *  - solo dígitos    → cédula/RUC (prefijo, índice único)
     *  - formato placa   → placa normalizada (prefijo, índice único)
     *  - texto           → nombre del cliente
     */
    private function aplicarBusqueda(Builder $q): void
    {
        $t = trim($this->buscar);
        if ($t === '') {
            return;
        }

        if (preg_match('/^OT-?\d{4}/i', $t)) {
            $q->where('codigo', 'like', strtoupper($t).'%');
        } elseif (ctype_digit($t)) {
            $q->whereHas('cliente', fn (Builder $c) => $c->where('numero_documento', 'like', $t.'%'));
        } elseif (preg_match('/^[A-Za-z]{2,3}[\s\-]?\d{1,4}[A-Za-z]?$/', $t)) {
            $q->whereHas('vehiculo', fn (Builder $v) => $v->where('placa', 'like', Placa::normalizar($t).'%'));
        } else {
            $q->whereHas('cliente', fn (Builder $c) => $c->where('nombres', 'like', '%'.$t.'%'));
        }
    }

    public function render()
    {
        // Solo las columnas que pinta la tabla (evita traer checklist JSON, diagnóstico, observaciones…).
        $q = OrdenTrabajo::query()
            ->select(['id', 'codigo', 'fecha_ingreso', 'vehiculo_id', 'cliente_id', 'tecnico_id', 'total', 'estado', 'estado_cobro', 'forma_cobro'])
            ->with([
                'vehiculo:id,placa,modelo_id',
                'vehiculo.modelo:id,marca_id,nombre',
                'vehiculo.modelo.marca:id,nombre',
                'cliente:id,nombres,numero_documento',
                'tecnico:id,nombres,apellidos',
            ])
            ->orderByDesc('fecha_ingreso');

        match ($this->estado) {
            'activas' => $q->activas(),
            'todas' => null,
            default => $q->where('estado', $this->estado),
        };

        $this->aplicarBusqueda($q);

        $conteo = OrdenTrabajo::query()
            ->selectRaw('estado, COUNT(*) as n')
            ->whereIn('estado', OrdenTrabajo::ESTADOS_ACTIVOS)
            ->groupBy('estado')
            ->pluck('n', 'estado');

        // Vista por defecto (activas, sin búsqueda): el total ya lo dio el KPI, se ahorra el COUNT(*) del paginador.
        $total = $this->estado === 'activas' && trim($this->buscar) === '' ? (int) $conteo->sum() : null;

        return view('livewire.ordenes.listado', [
            'ordenes' => $q->paginate(20, ['*'], 'page', null, $total),
            'kpi' => [
                'activas' => (int) $conteo->sum(),
                'en_proceso' => (int) ($conteo['en_proceso'] ?? 0),
                'listo_entrega' => (int) ($conteo['listo_entrega'] ?? 0),
                'citas' => Cita::where('estado', 'pendiente')->count(),
            ],
        ]);
    }
}
