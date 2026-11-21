<?php

namespace App\Livewire\Vehiculos;

use App\Models\OrdenTrabajo;
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
        // Una sola consulta de OTs no canceladas; de ahí salen historial, en curso y la última OT.
        $ordenes = $this->vehiculo->ordenes()
            ->where('estado', '!=', 'cancelado')
            ->orderByDesc('fecha_ingreso')
            ->get(['id', 'vehiculo_id', 'codigo', 'fecha_ingreso', 'estado', 'total', 'kilometraje_ingreso', 'proximo_kilometraje', 'tecnico_id']);

        // Historial de mantenimientos = OTs entregadas
        $historial = $ordenes->where('estado', 'entregado')->values()
            ->load(['items:id,orden_id,descripcion', 'tecnico:id,nombres,apellidos']);

        return view('livewire.vehiculos.ficha', [
            'historial' => $historial,
            'activas' => $ordenes->whereIn('estado', OrdenTrabajo::ESTADOS_ACTIVOS)->values(),
            'proximoKm' => $this->vehiculo->proximoKilometrajeDesde($ordenes->first()),
        ])->title('Vehículo '.$this->vehiculo->placa_formateada);
    }
}
