<?php

namespace App\Livewire\Publico;

use App\Models\Cliente;
use App\Models\Vehiculo;
use App\Support\Placa;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.publico')]
#[Title('Consulte su vehículo')]
class Inicio extends Component
{
    public string $tipo = 'placa';

    public string $valor = '';

    /** Vehículos del cliente cuando consulta por cédula/RUC y tiene varios. */
    public array $opciones = [];

    public function consultar()
    {
        $this->opciones = [];
        $this->resetErrorBag();

        // Las acciones Livewire no pasan por el throttle de la ruta: limitador propio por IP.
        $clave = 'consulta-publica:'.request()->ip();
        if (RateLimiter::tooManyAttempts($clave, 15)) {
            $this->addError('valor', 'Demasiadas consultas. Intente nuevamente en '.RateLimiter::availableIn($clave).' segundos.');

            return null;
        }
        RateLimiter::hit($clave, 60);

        $valor = trim($this->valor);

        if ($this->tipo === 'placa') {
            $this->validate(['valor' => ['required', 'string', 'max:10']], [], ['valor' => 'placa']);
            $vehiculo = Vehiculo::where('placa', Placa::normalizar($valor))->where('activo', true)->first();
            if (! $vehiculo) {
                $this->addError('valor', 'No encontramos un vehículo registrado con esa placa.');

                return null;
            }

            return $this->redirectRoute('publico.vehiculo', $vehiculo->qr_token, navigate: true);
        }

        $this->validate(['valor' => ['required', 'regex:/^\d{10}(\d{3})?$/']], [
            'valor.regex' => 'Ingrese una cédula (10 dígitos) o RUC (13 dígitos).',
        ], ['valor' => 'cédula/RUC']);

        // Coincidencia EXACTA de documento (no por prefijo) para evitar barridos.
        $cliente = Cliente::where('numero_documento', $valor)->first();
        $vehiculos = $cliente?->vehiculos()->where('activo', true)->with('modelo.marca')->orderBy('placa')->get() ?? collect();

        if ($vehiculos->isEmpty()) {
            $this->addError('valor', 'No encontramos vehículos asociados a ese documento.');

            return null;
        }

        if ($vehiculos->count() === 1) {
            return $this->redirectRoute('publico.vehiculo', $vehiculos->first()->qr_token, navigate: true);
        }

        $this->opciones = $vehiculos->map(fn ($v) => [
            'token' => $v->qr_token,
            'placa' => $v->placa_formateada,
            'detalle' => $v->modelo->marca->nombre.' '.$v->modelo->nombre.' '.$v->anio.' · '.$v->color,
        ])->all();

        return null;
    }

    public function updatedTipo(): void
    {
        $this->reset(['valor', 'opciones']);
        $this->resetErrorBag();
    }

    public function render()
    {
        return view('livewire.publico.inicio');
    }
}
