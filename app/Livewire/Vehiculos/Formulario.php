<?php

namespace App\Livewire\Vehiculos;

use App\Models\Cliente;
use App\Models\Modelo;
use App\Models\Vehiculo;
use App\Rules\PlacaEcuador;
use App\Support\Catalogos;
use App\Support\Placa;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Alta/edición completa del vehículo (incluye VIN, motor, transmisión, intervalo). */
#[Layout('components.layouts.panel')]
class Formulario extends Component
{
    public ?Vehiculo $vehiculo = null;

    public ?int $cliente_id = null;

    public string $buscarCliente = '';

    public string $placa = '';

    public string $marca = '';

    public string $modelo = '';

    public $anio = '';

    public string $color = '';

    public string $vin = '';

    public string $numero_motor = '';

    public string $tipo_combustible = 'gasolina';

    public string $transmision = 'manual';

    public $intervalo_mantenimiento_km = null;

    public bool $activo = true;

    public function mount(?Vehiculo $vehiculo = null): void
    {
        $this->intervalo_mantenimiento_km = config('taller.mantenimiento.intervalo_km');

        if ($vehiculo?->exists) {
            $vehiculo->load('modelo.marca');
            // Sin relaciones en la propiedad pública: Livewire re-consulta en cada roundtrip todo lo cargado.
            $this->vehiculo = $vehiculo->withoutRelations();
            $this->cliente_id = $vehiculo->cliente_id;
            $this->placa = $vehiculo->placa_formateada;
            $this->marca = $vehiculo->modelo->marca->nombre;
            $this->modelo = $vehiculo->modelo->nombre;
            $this->anio = $vehiculo->anio;
            $this->color = $vehiculo->color;
            $this->vin = (string) $vehiculo->vin;
            $this->numero_motor = (string) $vehiculo->numero_motor;
            $this->tipo_combustible = $vehiculo->tipo_combustible;
            $this->transmision = $vehiculo->transmision;
            $this->intervalo_mantenimiento_km = $vehiculo->intervalo_mantenimiento_km;
            $this->activo = $vehiculo->activo;
        } else {
            $this->cliente_id = Cliente::find(request()->integer('cliente'))?->id;
        }
    }

    #[Computed]
    public function cliente(): ?Cliente
    {
        return $this->cliente_id ? Cliente::find($this->cliente_id) : null;
    }

    #[Computed]
    public function resultadosCliente()
    {
        return mb_strlen(trim($this->buscarCliente)) >= 2
            ? Cliente::buscar($this->buscarCliente)->orderBy('nombres')->limit(8)->get()
            : collect();
    }

    public function seleccionarCliente(int $id): void
    {
        $this->cliente_id = $id;
        $this->buscarCliente = '';
    }

    public function updatedMarca(): void
    {
        $this->modelo = '';
    }

    #[Computed]
    public function modelosSugeridos()
    {
        return Catalogos::modelosDe($this->marca);
    }

    public function guardar()
    {
        $this->placa = Placa::normalizar($this->placa);
        $this->vin = strtoupper(trim($this->vin));

        $this->validate([
            'cliente_id' => ['required', 'exists:clientes,id'],
            'placa' => ['required', new PlacaEcuador, Rule::unique('vehiculos', 'placa')->ignore($this->vehiculo?->id)],
            'marca' => ['required', 'string', 'max:60'],
            'modelo' => ['required', 'string', 'max:80'],
            'anio' => ['required', 'integer', 'min:1950', 'max:'.(date('Y') + 1)],
            'color' => ['required', 'string', 'max:40'],
            'vin' => ['nullable', 'alpha_num', 'size:17'],
            'numero_motor' => ['nullable', 'string', 'max:40'],
            'tipo_combustible' => ['required', Rule::in(array_keys(Vehiculo::COMBUSTIBLES))],
            'transmision' => ['required', Rule::in(array_keys(Vehiculo::TRANSMISIONES))],
            'intervalo_mantenimiento_km' => ['required', 'integer', 'min:500', 'max:100000'],
            'activo' => ['boolean'],
        ], [], ['cliente_id' => 'propietario', 'anio' => 'año', 'vin' => 'VIN/chasis', 'intervalo_mantenimiento_km' => 'intervalo de mantenimiento']);

        $datos = [
            'cliente_id' => $this->cliente_id,
            'placa' => $this->placa,
            'modelo_id' => Modelo::resolver($this->marca, $this->modelo)->id,
            'anio' => (int) $this->anio,
            'color' => trim($this->color),
            'vin' => $this->vin ?: null,
            'numero_motor' => trim($this->numero_motor) ?: null,
            'tipo_combustible' => $this->tipo_combustible,
            'transmision' => $this->transmision,
            'intervalo_mantenimiento_km' => (int) $this->intervalo_mantenimiento_km,
            'activo' => $this->activo,
        ];

        if ($this->vehiculo) {
            $this->vehiculo->update($datos);
            $v = $this->vehiculo;
        } else {
            $v = Vehiculo::create($datos);
        }

        session()->flash('ok', 'Vehículo '.$v->placa_formateada.' guardado.');

        return $this->redirectRoute('vehiculos.show', $v, navigate: true);
    }

    public function render()
    {
        return view('livewire.vehiculos.formulario', [
            'marcas' => Catalogos::marcas(),
        ])->title($this->vehiculo ? 'Editar '.$this->vehiculo->placa_formateada : 'Nuevo vehículo');
    }
}
