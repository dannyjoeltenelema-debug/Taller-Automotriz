<?php

namespace App\Observers;

use App\Models\Empresa;
use App\Models\OrdenTrabajo;
use App\Models\OtEstado;
use App\Services\GeneradorCodigoOT;
use Illuminate\Support\Facades\Auth;

class OrdenTrabajoObserver
{
    public function __construct(private GeneradorCodigoOT $generador) {}

    /** Código secuencial atómico (requiere transacción abierta). */
    public function creating(OrdenTrabajo $orden): void
    {
        $orden->codigo ??= $this->generador->siguiente();
        // IVA histórico: la OT guarda el % vigente al crearse (no cambia si luego cambia el de empresa).
        $orden->iva_porcentaje ??= Empresa::ivaPorcentaje();
    }

    public function created(OrdenTrabajo $orden): void
    {
        $this->registrarEstado($orden, null, $orden->estado ?? 'recibido');
        $this->actualizarKilometrajeVehiculo($orden);
    }

    public function updating(OrdenTrabajo $orden): void
    {
        if ($orden->isDirty('estado') && $orden->estado === 'entregado' && ! $orden->fecha_entrega) {
            $orden->fecha_entrega = now();
        }
    }

    /** Si cambió el estado, deja traza en ot_estados. */
    public function updated(OrdenTrabajo $orden): void
    {
        if ($orden->wasChanged('estado')) {
            $this->registrarEstado($orden, $orden->getOriginal('estado'), $orden->estado);
        }

        if ($orden->wasChanged('kilometraje_ingreso')) {
            $this->actualizarKilometrajeVehiculo($orden);
        }
    }

    private function registrarEstado(OrdenTrabajo $orden, ?string $anterior, string $nuevo): void
    {
        OtEstado::create([
            'orden_id' => $orden->id,
            'estado_anterior' => $anterior,
            'estado_nuevo' => $nuevo,
            'empleado_id' => Auth::user()?->empleado?->id,
            'nota' => $orden->notaCambioEstado,
        ]);
        $orden->notaCambioEstado = null;
    }

    /** kilometraje_actual del vehículo es un cache del mayor km registrado. */
    private function actualizarKilometrajeVehiculo(OrdenTrabajo $orden): void
    {
        $vehiculo = $orden->vehiculo;
        if ($vehiculo && $orden->kilometraje_ingreso > (int) $vehiculo->kilometraje_actual) {
            $vehiculo->update(['kilometraje_actual' => $orden->kilometraje_ingreso]);
        }
    }
}
