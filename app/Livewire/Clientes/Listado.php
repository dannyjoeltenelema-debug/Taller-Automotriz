<?php

namespace App\Livewire\Clientes;

use App\Models\Cliente;
use App\Rules\CedulaRucEcuador;
use App\Support\Catalogos;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.panel')]
#[Title('Clientes')]
class Listado extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $buscar = '';

    public bool $modal = false;

    public ?int $editandoId = null;

    public string $tipo_documento = 'cedula';

    public string $numero_documento = '';

    public string $nombres = '';

    public string $telefono = '';

    public string $email = '';

    public string $direccion = '';

    public $empresa_colaboradora_id = '';

    public function updatedBuscar(): void
    {
        $this->resetPage();
    }

    public function nuevo(): void
    {
        $this->reset(['editandoId', 'numero_documento', 'nombres', 'telefono', 'email', 'direccion', 'empresa_colaboradora_id']);
        $this->tipo_documento = 'cedula';
        $this->resetValidation();
        $this->modal = true;
    }

    public function editar(int $id): void
    {
        $c = Cliente::findOrFail($id);
        $this->resetValidation();
        $this->editandoId = $c->id;
        $this->tipo_documento = $c->tipo_documento;
        $this->numero_documento = $c->numero_documento;
        $this->nombres = $c->nombres;
        $this->telefono = $c->telefono;
        $this->email = (string) $c->email;
        $this->direccion = (string) $c->direccion;
        $this->empresa_colaboradora_id = (string) $c->empresa_colaboradora_id;
        $this->modal = true;
    }

    protected function rules(): array
    {
        return [
            'tipo_documento' => ['required', Rule::in(array_keys(Cliente::TIPOS_DOCUMENTO))],
            'numero_documento' => array_filter([
                'required', 'string', 'max:20',
                Rule::unique('clientes', 'numero_documento')->ignore($this->editandoId),
                match ($this->tipo_documento) {
                    'cedula' => 'digits:10', 'ruc' => 'digits:13', default => null
                },
                $this->tipo_documento !== 'pasaporte' ? new CedulaRucEcuador($this->tipo_documento) : null,
            ]),
            'nombres' => ['required', 'string', 'max:150'],
            'telefono' => ['required', 'regex:/^\+?\d{9,13}$/'],
            'email' => ['nullable', 'email', 'max:120'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'empresa_colaboradora_id' => ['nullable', Rule::exists('empresas_colaboradoras', 'id')->whereNull('deleted_at')],
        ];
    }

    protected function validationAttributes(): array
    {
        return ['numero_documento' => 'cédula/RUC', 'nombres' => 'nombre', 'telefono' => 'teléfono', 'email' => 'correo'];
    }

    public function guardar(): void
    {
        $datos = $this->validate();
        $datos['numero_documento'] = trim($datos['numero_documento']);
        $datos['email'] = $datos['email'] ?: null;
        $datos['direccion'] = $datos['direccion'] ?: null;
        $datos['empresa_colaboradora_id'] = $datos['empresa_colaboradora_id'] ?: null;

        $nuevo = ! $this->editandoId;
        $cliente = Cliente::updateOrCreate(['id' => $this->editandoId], $datos);
        if ($nuevo) {
            $this->dispatch('fila-nueva', id: 'cli-'.$cliente->id);
        }

        $this->modal = false;
        $this->dispatch('toast', mensaje: 'Cliente guardado.');
    }

    public function render()
    {
        return view('livewire.clientes.listado', [
            'clientes' => Cliente::buscar($this->buscar)
                ->select(['id', 'tipo_documento', 'numero_documento', 'nombres', 'telefono', 'email', 'empresa_colaboradora_id'])
                ->with(['empresaColaboradora:id,nombre,porcentaje_descuento', 'vehiculos:id,cliente_id,placa'])
                ->withCount('ordenes')
                ->orderBy('nombres')
                ->paginate(config('taller.paginacion.clientes')),
            'convenios' => Catalogos::convenios(),
        ]);
    }
}
