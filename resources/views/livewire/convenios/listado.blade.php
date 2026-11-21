<div>
    <div class="flex flex-wrap items-end justify-between gap-2 mb-3">
        <div>
            <h1 class="page-title">Empresas colaboradoras</h1>
            <div class="page-sub">Convenios corporativos: sus clientes reciben el % de descuento automáticamente en la OT</div>
        </div>
        <button class="btn btn-primary" wire:click="nuevo">+ Nueva empresa</button>
    </div>

    <div class="panel tbl-wrap">
        <table class="tbl">
            <thead><tr><th>Empresa</th><th>RUC</th><th class="num">% descuento</th><th>Contacto</th><th>Teléfono</th><th class="num">Clientes</th><th></th></tr></thead>
            <tbody>
            @forelse ($empresas as $e)
                <tr wire:key="emp-{{ $e->id }}" data-fila="emp-{{ $e->id }}" wire:transition="conv-{{ $e->id }}">
                    <td class="font-medium">{{ $e->nombre }}</td>
                    <td>{{ $e->ruc ?? '—' }}</td>
                    <td class="num">{{ number_format($e->porcentaje_descuento, 2) }} %</td>
                    <td>{{ $e->contacto ?? '—' }}</td>
                    <td>{{ $e->telefono ?? '—' }}</td>
                    <td class="num">{{ $e->clientes_count }}</td>
                    <td class="text-right whitespace-nowrap">
                        <button class="btn btn-sm" wire:click="editar({{ $e->id }})">Editar</button>
                        <button class="btn btn-sm btn-danger" wire:click="confirmarEliminar({{ $e->id }})">Eliminar</button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center muted py-6">Sin convenios registrados.</td></tr>
            @endforelse
            </tbody>
        </table>
        @if ($empresas->hasPages())<div class="px-3 py-2 border-t border-[var(--c-border)]">{{ $empresas->links() }}</div>@endif
    </div>

    @if ($modal)
        <div class="modal-back" wire:transition>
            <form class="modal max-w-[560px]" wire:submit="guardar">
                <div class="modal-head"><span>{{ $editandoId ? 'Editar empresa colaboradora' : 'Nueva empresa colaboradora' }}</span><button type="button" wire:click="$set('modal', false)">✕</button></div>
                <div class="p-4 grid sm:grid-cols-2 gap-3">
                    <x-campo label="Nombre" :req="true" error="nombre" class="sm:col-span-2"><input class="inp" wire:model="nombre"></x-campo>
                    <x-campo label="RUC" error="ruc"><input class="inp" wire:model="ruc" inputmode="numeric" maxlength="13"></x-campo>
                    <x-campo label="% de descuento" :req="true" error="porcentaje_descuento"><input type="number" step="0.01" min="0" max="100" class="inp" wire:model="porcentaje_descuento"></x-campo>
                    <x-campo label="Contacto" error="contacto"><input class="inp" wire:model="contacto"></x-campo>
                    <x-campo label="Teléfono" error="telefono"><input class="inp" wire:model="telefono"></x-campo>
                </div>
                <div class="modal-foot">
                    <span class="muted text-[12px] mr-auto" wire:dirty>Cambios sin guardar</span>
                    <button type="button" class="btn" wire:click="$set('modal', false)">Cancelar</button>
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="guardar">
                        <span wire:loading.remove wire:target="guardar">Guardar</span><span wire:loading wire:target="guardar">Guardando…</span>
                    </button>
                </div>
            </form>
        </div>
    @endif

    @if ($porEliminar)
        <div class="modal-back" wire:transition wire:key="modal-eliminar">
            <div class="modal max-w-[480px]" role="dialog" aria-modal="true">
                <div class="modal-head"><span>Eliminar empresa colaboradora</span><button type="button" wire:click="$set('eliminandoId', null)">✕</button></div>
                <div class="p-4 space-y-2">
                    <p class="font-semibold">{{ $porEliminar->nombre }}</p>
                    <p>Se eliminará el convenio. {{ $porEliminar->clientes_count }} {{ $porEliminar->clientes_count === 1 ? 'cliente quedará' : 'clientes quedarán' }} en 'No aplica'. Las órdenes ya emitidas no cambian.</p>
                    <p class="muted text-[12.5px]">El superadmin puede restaurarlo desde Sistema &gt; Registros eliminados (los clientes no se vuelven a vincular solos).</p>
                </div>
                <div class="modal-foot">
                    <button type="button" class="btn" wire:click="$set('eliminandoId', null)">Cancelar</button>
                    <button type="button" class="btn btn-primary" wire:click="eliminar" wire:loading.attr="disabled" wire:target="eliminar">
                        <span wire:loading.remove wire:target="eliminar">Eliminar</span><span wire:loading wire:target="eliminar">Eliminando…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
