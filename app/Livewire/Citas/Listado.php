<?php

namespace App\Livewire\Citas;

use App\Models\Cita;
use App\Models\Sede;
use App\Models\Vehiculo;
use App\Rules\PlacaEcuador;
use App\Support\Placa;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.panel')]
#[Title('Citas / Solicitudes')]
class Listado extends Component
{
    use WithPagination;

    #[Url(except: 'abiertas')]
    public string $estado = 'abiertas';

    #[Url(except: '')]
    public string $fecha = '';

    public string $aviso = '';

    // Alta desde recepción
    public bool $modal = false;

    public array $form = [];

    public function updatedEstado(): void
    {
        $this->resetPage();
    }

    public function updatedFecha(): void
    {
        $this->resetPage();
    }

    public function confirmar(int $id): void
    {
        Cita::whereKey($id)->where('estado', 'pendiente')->update(['estado' => 'confirmada']);
        $this->aviso = 'Cita confirmada.';
    }

    public function cancelar(int $id): void
    {
        Cita::whereKey($id)->whereIn('estado', ['pendiente', 'confirmada'])->update(['estado' => 'cancelada']);
        $this->aviso = 'Cita cancelada.';
    }

    /** Abre el formulario de OT precargado; al guardarse la OT se enlaza orden_id y la cita pasa a "atendida". */
    public function convertir(int $id)
    {
        $cita = Cita::findOrFail($id);
        if ($cita->orden_id) {
            return $this->redirectRoute('ordenes.edit', $cita->orden_id, navigate: true);
        }

        return $this->redirectRoute('ordenes.create', ['cita' => $cita->id], navigate: true);
    }

    public function nueva(): void
    {
        $this->resetValidation();
        $this->form = [
            'sede_id' => auth()->user()->empleado?->sede_id ?? Sede::value('id'),
            'placa' => '', 'cliente_nombre' => '', 'cliente_telefono' => '',
            'fecha_deseada' => now()->addDay()->toDateString(), 'hora_inicio' => Cita::FRANJAS[0],
            'motivo' => '', 'observacion' => '',
        ];
        $this->modal = true;
    }

    /** Al escribir la placa, si el vehículo existe se completan nombre y teléfono. */
    public function updatedFormPlaca(): void
    {
        if ($v = Vehiculo::with('cliente')->where('placa', Placa::normalizar($this->form['placa']))->first()) {
            $this->form['cliente_nombre'] = $v->cliente->nombres;
            $this->form['cliente_telefono'] = $v->cliente->telefono;
        }
    }

    public function guardar(): void
    {
        $this->validate([
            'form.sede_id' => ['required', 'exists:sedes,id'],
            'form.placa' => ['required', new PlacaEcuador],
            'form.cliente_nombre' => ['required', 'string', 'max:150'],
            'form.cliente_telefono' => ['required', 'regex:/^\+?\d{9,13}$/'],
            'form.fecha_deseada' => ['required', 'date', 'after_or_equal:today'],
            'form.hora_inicio' => ['required', Rule::in(Cita::FRANJAS)],
            'form.motivo' => ['required', 'string', 'max:150'],
            'form.observacion' => ['nullable', 'string', 'max:1000'],
        ], [], [
            'form.placa' => 'placa', 'form.cliente_nombre' => 'nombre', 'form.cliente_telefono' => 'teléfono',
            'form.fecha_deseada' => 'fecha', 'form.hora_inicio' => 'franja', 'form.motivo' => 'motivo',
        ]);

        Cita::create($this->form + [
            'vehiculo_id' => Vehiculo::porPlaca($this->form['placa'])?->id,
            'origen' => 'recepcion',
            'estado' => 'confirmada',
        ]);

        $this->modal = false;
        $this->aviso = 'Cita registrada.';
    }

    public function render()
    {
        $citas = Cita::query()
            ->with(['vehiculo.modelo.marca', 'orden', 'sede'])
            ->when($this->estado === 'abiertas', fn ($q) => $q->whereIn('estado', ['pendiente', 'confirmada']))
            ->when(! in_array($this->estado, ['abiertas', 'todas']), fn ($q) => $q->where('estado', $this->estado))
            ->when($this->fecha, fn ($q) => $q->whereDate('fecha_deseada', $this->fecha))
            ->orderBy('fecha_deseada')->orderBy('hora_inicio')
            ->paginate(25);

        return view('livewire.citas.listado', [
            'citas' => $citas,
            'sedes' => Sede::where('activa', true)->orderBy('nombre')->get(),
        ]);
    }
}
