<?php

namespace App\Livewire\Configuracion;

use App\Models\ChecklistItem;
use App\Models\Marca;
use App\Models\Modelo;
use App\Models\Servicio;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Servicios fijos, checklist de recepción y marcas/modelos. */
#[Layout('components.layouts.panel')]
#[Title('Catálogos')]
class Catalogos extends Component
{
    #[Url]
    public string $tab = 'servicios';

    public string $aviso = '';

    // Servicio
    public ?int $servicioId = null;

    public string $s_nombre = '';

    public string $s_descripcion = '';

    public $s_precio = '';

    // Checklist
    public string $c_nombre = '';

    // Marca / modelo
    public string $m_marca = '';

    public string $m_modelo = '';

    public function editarServicio(int $id): void
    {
        $s = Servicio::findOrFail($id);
        $this->servicioId = $s->id;
        $this->s_nombre = $s->nombre;
        $this->s_descripcion = (string) $s->descripcion;
        $this->s_precio = (string) $s->precio_referencial;
    }

    public function guardarServicio(): void
    {
        $this->validate([
            's_nombre' => ['required', 'string', 'max:120'],
            's_descripcion' => ['nullable', 'string', 'max:255'],
            's_precio' => ['required', 'numeric', 'min:0', 'max:99999'],
        ], [], ['s_nombre' => 'nombre', 's_precio' => 'precio']);

        Servicio::updateOrCreate(['id' => $this->servicioId], [
            'nombre' => $this->s_nombre, 'descripcion' => $this->s_descripcion ?: null, 'precio_referencial' => $this->s_precio,
        ]);
        $this->reset(['servicioId', 's_nombre', 's_descripcion', 's_precio']);
        $this->aviso = 'Servicio guardado.';
    }

    public function toggleServicio(int $id): void
    {
        $s = Servicio::findOrFail($id);
        $s->update(['activo' => ! $s->activo]);
    }

    public function agregarChecklist(): void
    {
        $this->validate(['c_nombre' => ['required', 'string', 'max:100']], [], ['c_nombre' => 'ítem']);
        ChecklistItem::create(['nombre' => $this->c_nombre, 'orden' => (int) ChecklistItem::max('orden') + 1]);
        $this->reset('c_nombre');
    }

    public function toggleChecklist(int $id): void
    {
        $c = ChecklistItem::findOrFail($id);
        $c->update(['activo' => ! $c->activo]);
    }

    public function moverChecklist(int $id, int $dir): void
    {
        $items = ChecklistItem::orderBy('orden')->orderBy('id')->get()->values();
        $i = $items->search(fn ($c) => $c->id === $id);
        $j = $i + $dir;
        if ($i === false || $j < 0 || $j >= $items->count()) {
            return;
        }
        [$a, $b] = [$items[$i], $items[$j]];
        $items->each(fn ($c, $k) => $c->orden = $k + 1);
        [$a->orden, $b->orden] = [$b->orden, $a->orden];
        $items->each->save();
    }

    public function agregarModelo(): void
    {
        $this->validate([
            'm_marca' => ['required', 'string', 'max:60'],
            'm_modelo' => ['required', 'string', 'max:80'],
        ], [], ['m_marca' => 'marca', 'm_modelo' => 'modelo']);

        Modelo::resolver($this->m_marca, $this->m_modelo);
        $this->reset('m_modelo');
        $this->aviso = 'Modelo agregado.';
    }

    public function render()
    {
        return view('livewire.configuracion.catalogos', [
            'servicios' => Servicio::orderByDesc('activo')->orderBy('nombre')->get(),
            'checklist' => ChecklistItem::orderBy('orden')->orderBy('id')->get(),
            'marcas' => Marca::with(['modelos' => fn ($q) => $q->orderBy('nombre')])->orderBy('nombre')->get(),
        ]);
    }
}
