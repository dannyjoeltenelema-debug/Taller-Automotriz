<div>
    <div class="mb-3">
        <h1 class="page-title">Catálogos</h1>
        <div class="page-sub">Servicios fijos, checklist de recepción y marcas/modelos</div>
    </div>

    <div class="flex border-b border-[var(--c-border)] mb-3">
        @foreach (['servicios' => 'Servicios fijos', 'checklist' => 'Checklist de recepción', 'marcas' => 'Marcas y modelos'] as $k => $v)
            <button wire:click="$set('tab', '{{ $k }}')" @class(['px-4 py-2 -mb-px border-b-2', 'border-[var(--c-accent)] text-[var(--c-accent)] font-semibold' => $tab === $k, 'border-transparent' => $tab !== $k])>{{ $v }}</button>
        @endforeach
    </div>


    @if ($tab === 'servicios')
        <div class="grid lg:grid-cols-3 gap-3">
            <div class="panel lg:col-span-2 tbl-wrap">
                <table class="tbl">
                    <thead><tr><th>Servicio</th><th>Descripción</th><th class="num">Precio ref.</th><th>Estado</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($servicios as $s)
                        <tr wire:key="s-{{ $s->id }}" @class(['muted' => ! $s->activo])>
                            <td class="font-medium">{{ $s->nombre }}</td>
                            <td class="text-[12px]">{{ $s->descripcion }}</td>
                            <td class="num">{{ \App\Support\Dinero::mostrar($s->precio_referencial) }}</td>
                            <td>{{ $s->activo ? 'Activo' : 'Inactivo' }}</td>
                            <td class="text-right whitespace-nowrap">
                                <button class="btn btn-sm" wire:click="editarServicio({{ $s->id }})">Editar</button>
                                <button class="btn btn-sm" wire:click="toggleServicio({{ $s->id }})">{{ $s->activo ? 'Desactivar' : 'Activar' }}</button>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <form class="panel" wire:submit="guardarServicio">
                <div class="panel-head">{{ $servicioId ? 'Editar servicio' : 'Nuevo servicio' }}</div>
                <div class="panel-body space-y-3">
                    <x-campo label="Nombre" :req="true" error="s_nombre"><input class="inp" wire:model="s_nombre"></x-campo>
                    <x-campo label="Descripción" error="s_descripcion"><input class="inp" wire:model="s_descripcion"></x-campo>
                    <x-campo label="Precio referencial (USD, sin IVA)" :req="true" error="s_precio"><input type="number" step="0.01" class="inp" wire:model="s_precio"></x-campo>
                    <div class="flex justify-end gap-2">
                        @if ($servicioId)<button type="button" class="btn" wire:click="$set('servicioId', null)">Nuevo</button>@endif
                        <button class="btn btn-primary">Guardar</button>
                    </div>
                </div>
            </form>
        </div>
    @elseif ($tab === 'checklist')
        <div class="panel max-w-[640px]">
            <form class="panel-head" wire:submit="agregarChecklist">
                <input class="inp" wire:model="c_nombre" placeholder="Nuevo ítem de checklist">
                <button class="btn btn-primary">Agregar</button>
            </form>
            @error('c_nombre')<div class="err px-3">{{ $message }}</div>@enderror
            <table class="tbl">
                <tbody>
                @foreach ($checklist as $c)
                    <tr wire:key="c-{{ $c->id }}" @class(['muted' => ! $c->activo])>
                        <td class="w-10 num">{{ $loop->iteration }}</td>
                        <td>{{ $c->nombre }}</td>
                        <td class="text-right whitespace-nowrap">
                            <button class="btn btn-sm" wire:click="moverChecklist({{ $c->id }}, -1)" title="Subir">↑</button>
                            <button class="btn btn-sm" wire:click="moverChecklist({{ $c->id }}, 1)" title="Bajar">↓</button>
                            <button class="btn btn-sm" wire:click="toggleChecklist({{ $c->id }})">{{ $c->activo ? 'Desactivar' : 'Activar' }}</button>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="grid lg:grid-cols-3 gap-3">
            <div class="panel lg:col-span-2 tbl-wrap">
                <table class="tbl">
                    <thead><tr><th class="w-40">Marca</th><th>Modelos</th></tr></thead>
                    <tbody>
                    @foreach ($marcas as $m)
                        <tr wire:key="m-{{ $m->id }}"><td class="font-medium">{{ $m->nombre }}</td><td>{{ $m->modelos->pluck('nombre')->join(', ') }}</td></tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <form class="panel" wire:submit="agregarModelo">
                <div class="panel-head">Agregar modelo</div>
                <div class="panel-body space-y-3">
                    <x-campo label="Marca (existente o nueva)" :req="true" error="m_marca">
                        <input class="inp" list="dl-cat-marcas" wire:model="m_marca">
                        <datalist id="dl-cat-marcas">@foreach ($marcas as $m)<option value="{{ $m->nombre }}" wire:key="opt-{{ $m->nombre }}">@endforeach</datalist>
                    </x-campo>
                    <x-campo label="Modelo" :req="true" error="m_modelo"><input class="inp" wire:model="m_modelo"></x-campo>
                    <div class="flex justify-end"><button class="btn btn-primary">Agregar</button></div>
                </div>
            </form>
        </div>
    @endif
</div>
