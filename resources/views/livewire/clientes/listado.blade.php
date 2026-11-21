<div>
    <div class="flex flex-wrap items-end justify-between gap-2 mb-3">
        <div>
            <h1 class="page-title">Clientes</h1>
            <div class="page-sub">Personas y empresas propietarias de vehículos</div>
        </div>
        <button class="btn btn-primary" wire:click="nuevo">+ Nuevo cliente</button>
    </div>


    <div class="panel">
        <div class="panel-head">
            <input type="search" class="inp max-w-sm" wire:model.live.debounce.300ms="buscar" placeholder="Buscar por cédula/RUC, nombre o placa">
            <span class="muted text-[12px] font-normal">{{ $clientes->total() }} registros</span>
        </div>
        <div class="tbl-wrap relative">
            <x-skeleton-tabla target="buscar" />
            <table class="tbl">
                <thead><tr><th>Nombre / Razón social</th><th>Documento</th><th>Teléfono</th><th>Correo</th><th>Convenio</th><th>Vehículos</th><th class="num">OT</th><th></th></tr></thead>
                <tbody>
                @forelse ($clientes as $c)
                    <tr wire:key="cli-{{ $c->id }}" data-fila="cli-{{ $c->id }}">
                        <td class="font-medium">{{ $c->nombres }}</td>
                        <td class="whitespace-nowrap"><span class="muted">{{ \App\Models\Cliente::TIPOS_DOCUMENTO[$c->tipo_documento] }}</span> {{ $c->numero_documento }}</td>
                        <td>{{ $c->telefono }}</td>
                        <td>{{ $c->email ?? '—' }}</td>
                        <td>{{ $c->empresaColaboradora ? $c->empresaColaboradora->nombre.' ('.(float) $c->empresaColaboradora->porcentaje_descuento.'%)' : 'No aplica' }}</td>
                        <td>
                            @foreach ($c->vehiculos as $v)
                                <a wire:key="cli-{{ $c->id }}-veh-{{ $v->id }}" href="{{ route('vehiculos.show', $v) }}" wire:navigate class="link placa">{{ $v->placa_formateada }}</a>@if(! $loop->last), @endif
                            @endforeach
                        </td>
                        <td class="num">{{ $c->ordenes_count }}</td>
                        <td class="text-right whitespace-nowrap">
                            <button class="btn btn-sm" wire:click="editar({{ $c->id }})">Editar</button>
                            <a class="btn btn-sm" href="{{ route('vehiculos.create', ['cliente' => $c->id]) }}" wire:navigate>+ Vehículo</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center muted py-6">Sin clientes.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($clientes->hasPages())<div class="px-3 py-2 border-t border-[var(--c-border)]">{{ $clientes->links() }}</div>@endif
    </div>

    @if ($modal)
        <div class="modal-back" wire:transition>
            <form class="modal" wire:submit="guardar">
                <div class="modal-head"><span>{{ $editandoId ? 'Editar cliente' : 'Nuevo cliente' }}</span><button type="button" wire:click="$set('modal', false)">✕</button></div>
                <div class="p-4 grid sm:grid-cols-2 gap-3">
                    <x-campo label="Nombre / Razón social" :req="true" error="nombres" class="sm:col-span-2"><input class="inp" wire:model="nombres"></x-campo>
                    <x-campo label="Documento" :req="true" error="numero_documento">
                        <div class="flex gap-1">
                            <select class="inp w-28" wire:model.live="tipo_documento">
                                @foreach (\App\Models\Cliente::TIPOS_DOCUMENTO as $k => $v)<option value="{{ $k }}" wire:key="opt-{{ $k }}">{{ $v }}</option>@endforeach
                            </select>
                            <input class="inp" wire:model="numero_documento" inputmode="numeric">
                        </div>
                    </x-campo>
                    <x-campo label="Teléfono WhatsApp" :req="true" error="telefono"><input class="inp" wire:model="telefono" placeholder="09XXXXXXXX"></x-campo>
                    <x-campo label="Correo" error="email"><input type="email" class="inp" wire:model="email"></x-campo>
                    <x-campo label="Dirección" error="direccion"><input class="inp" wire:model="direccion"></x-campo>
                    <x-campo label="Empresa colaboradora / Descuento" error="empresa_colaboradora_id" class="sm:col-span-2">
                        <select class="inp" wire:model="empresa_colaboradora_id">
                            <option value="">No aplica</option>
                            @foreach ($convenios as $cv)
                                <option value="{{ $cv->id }}" wire:key="conv-{{ $cv->id }}">{{ $cv->nombre }} ({{ (float) $cv->porcentaje_descuento }}%)</option>
                            @endforeach
                        </select>
                    </x-campo>
                </div>
                <div class="modal-foot">
                    <span class="muted text-[12px] mr-auto" wire:dirty>Cambios sin guardar</span>
                    <button type="button" class="btn" wire:click="$set('modal', false)">Cancelar</button>
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="guardar"><span wire:loading.remove wire:target="guardar">Guardar</span><span wire:loading wire:target="guardar">Guardando…</span></button>
                </div>
            </form>
        </div>
    @endif
</div>
