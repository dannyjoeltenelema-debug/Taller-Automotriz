<?php

namespace App\Livewire\Vehiculos;

use App\Models\Vehiculo;
use App\Support\Placa;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.panel')]
#[Title('Vehículos')]
class Listado extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $buscar = '';

    public function updatedBuscar(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $t = trim($this->buscar);

        $vehiculos = Vehiculo::query()
            ->with(['modelo.marca', 'cliente'])
            ->when($t !== '', function (Builder $q) use ($t) {
                if (ctype_digit($t)) {
                    $q->whereHas('cliente', fn ($c) => $c->where('numero_documento', 'like', $t.'%'));
                } else {
                    $q->where(fn ($w) => $w->where('placa', 'like', Placa::normalizar($t).'%')
                        ->orWhereHas('cliente', fn ($c) => $c->where('nombres', 'like', '%'.$t.'%'))
                        ->orWhereHas('modelo', fn ($m) => $m->where('nombre', 'like', $t.'%')));
                }
            })
            ->orderBy('placa')
            ->paginate(25);

        return view('livewire.vehiculos.listado', compact('vehiculos'));
    }
}
