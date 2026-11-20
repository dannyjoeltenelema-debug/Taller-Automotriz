<?php

namespace App\Livewire\Vehiculos;

use App\Models\Vehiculo;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.panel')]
class Ficha extends Component
{
    public Vehiculo $vehiculo;

    public function mount(Vehiculo $vehiculo): void
    {
        $this->vehiculo = $vehiculo->load(['modelo.marca', 'cliente.empresaColaboradora']);
    }

    public function render()
    {
        return view('livewire.vehiculos.ficha', [
            // Historial de mantenimientos = OTs entregadas
            'historial' => $this->vehiculo->ordenes()->where('estado', 'entregado')
                ->with(['items', 'tecnico'])->orderByDesc('fecha_ingreso')->get(),
            'activas' => $this->vehiculo->ordenes()->activas()->orderByDesc('fecha_ingreso')->get(),
            'proximoKm' => $this->vehiculo->proximoKilometraje(),
        ])->title('Vehículo '.$this->vehiculo->placa_formateada);
    }
}
