<?php

namespace App\Livewire\Publico;

use App\Models\Cliente;
use App\Models\Vehiculo;
use App\Rules\CedulaRucEcuador;
use App\Rules\PlacaEcuador;
use App\Support\Placa;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
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
        if (RateLimiter::tooManyAttempts($clave, config('taller.portal.consultas_max'))) {
            $this->addError('valor', 'Demasiadas consultas. Intente nuevamente en '.RateLimiter::availableIn($clave).' segundos.');

            return null;
        }
        RateLimiter::hit($clave, config('taller.portal.consultas_ventana_seg'));

        if ($this->tipo === 'placa') {
            // Se normaliza ANTES de validar (pba-4521, "PBA 4521" → PBA4521) y se valida con la regla ecuatoriana.
            $placa = Placa::normalizar(mb_substr($this->valor, 0, 20));
            Validator::make(['valor' => $placa], ['valor' => ['bail', 'required', new PlacaEcuador]], [], ['valor' => 'placa'])->validate();

            // Una consulta por índice único (placa) y solo la columna que se usa.
            $token = Vehiculo::where('placa', $placa)->where('activo', true)->value('qr_token');
            if (! $token) {
                $this->addError('valor', 'No encontramos un vehículo registrado con esa placa.');

                return null;
            }

            return $this->redirectRoute('publico.vehiculo', $token, navigate: true);
        }

        // Se quitan espacios y guiones antes de validar (módulo 10 / RUC con CedulaRucEcuador).
        $valor = preg_replace('/[\s\-.]/', '', mb_substr($this->valor, 0, 30));
        Validator::make(['valor' => $valor], ['valor' => ['bail', 'required', 'regex:/^\d{10}(\d{3})?$/', new CedulaRucEcuador]], [
            'valor.regex' => 'Ingrese una cédula (10 dígitos) o RUC (13 dígitos).',
        ], ['valor' => 'cédula/RUC'])->validate();

        // Coincidencia EXACTA de documento (no por prefijo) para evitar barridos.
        $vehiculos = Vehiculo::query()
            ->whereIn('cliente_id', Cliente::select('id')->where('numero_documento', $valor))
            ->where('activo', true)
            ->with(['modelo:id,marca_id,nombre', 'modelo.marca:id,nombre'])
            ->orderBy('placa')
            ->get(['id', 'qr_token', 'placa', 'modelo_id', 'anio', 'color']);

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
