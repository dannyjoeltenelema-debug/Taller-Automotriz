<div>
    <div class="flex flex-wrap items-end justify-between gap-2 mb-3">
        <div>
            <h1 class="page-title">Empresas colaboradoras</h1>
            <div class="page-sub">Convenios corporativos: sus clientes reciben el % de descuento automáticamente en la OT</div>
        </div>
        <button class="btn btn-primary" wire:click="nuevo">+ Nueva empresa</button>
    </div>

    @if ($aviso)<div class="alert alert-ok mb-3">{{ $aviso }}</div>@endif

    <div class="panel tbl-wrap">
        <table class="tbl">
            <thead><tr><th>Empresa</th><th>RUC</th><th class="num">% descuento</th><th>Contacto</th><th>Teléfono</th><th class="num">Clientes</th><th>Estado</th><th></th></tr></thead>
            <tbody>
            @forelse ($empresas as $e)
                <tr wire:key="emp-{{ $e->id }}">
                    <td class="font-medium">{{ $e->nombre }}</td>
                    <td>{{ $e->ruc ?? '—' }}</td>
                    <td class="num">{{ number_format($e->porcentaje_descuento, 2) }} %</td>
                    <td>{{ $e->contacto ?? '—' }}</td>
                    <td>{{ $e->telefono ?? '—' }}</td>
                    <td class="num">{{ $e->clientes_count }}</td>
                    <td>{!! $e->activo ? '<span class="badge st-listo_entrega">Activo</span>' : '<span class="badge st-entregado">Inactivo</span>' !!}</td>
                    <td class="text-right"><button class="btn btn-sm" wire:click="editar({{ $e->id }})">Editar</button></td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center muted py-6">Sin convenios registrados.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if ($modal)
        <div class="modal-back">
            <form class="modal max-w-[560px]" wire:submit="guardar">
                <div class="modal-head"><span>{{ $editandoId ? 'Editar empresa colaboradora' : 'Nueva empresa colaboradora' }}</span><button type="button" wire:click="$set('modal', false)">✕</button></div>
                <div class="p-4 grid sm:grid-cols-2 gap-3">
                    <x-campo label="Nombre" :req="true" error="nombre" class="sm:col-span-2"><input class="inp" wire:model="nombre"></x-campo>
                    <x-campo label="RUC" error="ruc"><input class="inp" wire:model="ruc" inputmode="numeric" maxlength="13"></x-campo>
                    <x-campo label="% de descuento" :req="true" error="porcentaje_descuento"><input type="number" step="0.01" min="0" max="100" class="inp" wire:model="porcentaje_descuento"></x-campo>
                    <x-campo label="Contacto" error="contacto"><input class="inp" wire:model="contacto"></x-campo>
                    <x-campo label="Teléfono" error="telefono"><input class="inp" wire:model="telefono"></x-campo>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="activo"> Convenio activo</label>
                </div>
                <div class="modal-foot">
                    <button type="button" class="btn" wire:click="$set('modal', false)">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    @endif
</div>
