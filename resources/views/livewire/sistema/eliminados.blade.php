<div>
    <div class="mb-3">
        <h1 class="page-title">Registros eliminados</h1>
        <div class="page-sub">Lo eliminado no se borra de la base de datos: desde aquí se puede restaurar</div>
    </div>

    <div class="panel">
        <div class="panel-head">Empresas colaboradoras</div>
        <div class="tbl-wrap">
            <table class="tbl">
                <thead><tr><th>Empresa</th><th>RUC</th><th class="num">% descuento</th><th>Eliminada el</th><th></th></tr></thead>
                <tbody>
                @forelse ($convenios as $c)
                    <tr wire:key="del-conv-{{ $c->id }}" wire:transition="del-conv-{{ $c->id }}">
                        <td class="font-medium">{{ $c->nombre }}</td>
                        <td>{{ $c->ruc ?? '—' }}</td>
                        <td class="num">{{ number_format($c->porcentaje_descuento, 2) }} %</td>
                        <td>{{ $c->deleted_at->format('d/m/Y H:i') }}</td>
                        <td class="text-right"><button class="btn btn-sm" wire:click="confirmarRestaurar({{ $c->id }})">Restaurar</button></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center muted py-6">No hay empresas colaboradoras eliminadas.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($convenios->hasPages())<div class="px-3 py-2 border-t border-[var(--c-border)]">{{ $convenios->links() }}</div>@endif
    </div>

    @if ($porRestaurar)
        <div class="modal-back" wire:transition wire:key="modal-restaurar">
            <div class="modal max-w-[460px]" role="dialog" aria-modal="true">
                <div class="modal-head"><span>Restaurar empresa colaboradora</span><button type="button" wire:click="$set('restaurandoId', null)">✕</button></div>
                <div class="p-4 space-y-2">
                    <p class="font-semibold">{{ $porRestaurar->nombre }}</p>
                    <p>El convenio vuelve a estar disponible para asignarlo a clientes.</p>
                    <p class="muted text-[12.5px]">Los clientes que tenía NO se vuelven a vincular solos: quedaron en 'No aplica' al eliminarlo. Asígnelos de nuevo desde Clientes si corresponde.</p>
                </div>
                <div class="modal-foot">
                    <button type="button" class="btn" wire:click="$set('restaurandoId', null)">Cancelar</button>
                    <button type="button" class="btn btn-primary" wire:click="restaurar" wire:loading.attr="disabled" wire:target="restaurar">
                        <span wire:loading.remove wire:target="restaurar">Restaurar</span><span wire:loading wire:target="restaurar">Restaurando…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
