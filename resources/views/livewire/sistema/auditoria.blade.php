@php use App\Models\Auditoria; @endphp
<div>
    <div class="flex flex-wrap items-end justify-between gap-2 mb-3">
        <div>
            <h1 class="page-title">Auditoría</h1>
            <div class="page-sub">Quién hizo qué y cuándo: altas, cambios campo por campo, eliminaciones y accesos al sistema</div>
        </div>
        <button class="btn" wire:click="exportarPdf" wire:loading.attr="disabled" wire:target="exportarPdf">
            <span wire:loading.remove wire:target="exportarPdf">Exportar PDF</span><span wire:loading wire:target="exportarPdf">Generando…</span>
        </button>
    </div>

    <div class="panel">
        <div class="panel-head flex-wrap justify-start">
            <input class="inp w-56" type="search" placeholder="Buscar usuario, cédula, IP o ID…" wire:model.live.debounce.300ms="buscar" aria-label="Buscar">
            <select class="inp w-auto" wire:model.live="usuario" aria-label="Usuario">
                <option value="">Todos los usuarios</option>
                @foreach ($this->usuarios as $u)<option value="{{ $u->id }}" wire:key="u-{{ $u->id }}">{{ $u->name }}</option>@endforeach
            </select>
            <select class="inp w-auto" wire:model.live="modulo" aria-label="Módulo">
                <option value="">Todos los módulos</option>
                <option value="acceso">Acceso (login / logout)</option>
                @foreach ($modulos as $clase => $nombre)<option value="{{ $clase }}" wire:key="m-{{ $loop->index }}">{{ $nombre }}</option>@endforeach
            </select>
            <select class="inp w-auto" wire:model.live="accion" aria-label="Acción">
                <option value="">Todas las acciones</option>
                @foreach ($acciones as $k => $v)<option value="{{ $k }}" wire:key="a-{{ $k }}">{{ $v }}</option>@endforeach
            </select>
            <label class="flex items-center gap-1 text-[12px] font-normal">Desde <input type="date" class="inp w-auto" wire:model.live="desde"></label>
            <label class="flex items-center gap-1 text-[12px] font-normal">Hasta <input type="date" class="inp w-auto" wire:model.live="hasta"></label>
            <button class="btn btn-sm" wire:click="limpiarFiltros">Limpiar</button>
            @error('desde')<span class="err">{{ $message }}</span>@enderror
            @error('hasta')<span class="err">{{ $message }}</span>@enderror
        </div>

        <div class="tbl-wrap relative">
            <x-skeleton-tabla target="buscar,usuario,modulo,accion,desde,hasta,limpiarFiltros" :filas="6" />
            <table class="tbl">
                <thead><tr><th>Fecha</th><th>Usuario</th><th>Acción</th><th>Módulo</th><th>Registro</th><th>IP</th><th>Cambios</th></tr></thead>
                <tbody>
                @forelse ($registros as $r)
                    @php $diff = $r->diff(); $abierto = $detalleId === $r->id; @endphp
                    <tr wire:key="aud-{{ $r->id }}">
                        <td class="whitespace-nowrap">{{ $r->created_at->format('d/m/Y H:i:s') }}</td>
                        <td>{{ $r->user?->name ?? '—' }} @if ($r->user)<span class="muted">({{ $r->user->cedula }})</span>@endif</td>
                        <td><span @class(['badge', 'st-cancelado' => in_array($r->accion, ['eliminado', 'login_fallido', 'bloqueado']), 'st-recibido' => ! in_array($r->accion, ['eliminado', 'login_fallido', 'bloqueado'])])>{{ $r->accion_label }}</span></td>
                        <td>{{ $r->modulo }}</td>
                        <td>{{ $r->auditable_id ? '#'.$r->auditable_id : '—' }}</td>
                        <td class="whitespace-nowrap">{{ $r->ip ?? '—' }}</td>
                        <td>
                            @if ($diff)
                                <button type="button" class="link text-[12.5px]" wire:click="verDetalle({{ $r->id }})" wire:loading.attr="disabled" wire:target="verDetalle({{ $r->id }})">
                                    {{ $abierto ? 'Ocultar' : 'Ver '.count($diff).' '.(count($diff) === 1 ? 'campo' : 'campos') }}
                                </button>
                                @if ($abierto)
                                <div class="mt-1 anim-aparece-arriba" wire:key="aud-det-{{ $r->id }}">
                                    <table class="text-[12px] w-full">
                                        <thead><tr class="muted"><td class="pr-3">Campo</td><td class="pr-3">Antes</td><td>Después</td></tr></thead>
                                        <tbody>
                                        @foreach ($diff as $campo => [$antes, $despues])
                                            <tr wire:key="aud-{{ $r->id }}-{{ $campo }}">
                                                <td class="pr-3 font-medium align-top">{{ str_replace('_', ' ', ucfirst($campo)) }}</td>
                                                <td class="pr-3 align-top muted break-all">{{ Auditoria::textoValor($antes) }}</td>
                                                <td class="align-top break-all">{{ Auditoria::textoValor($despues) }}</td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                @endif
                            @else
                                <span class="muted">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center muted py-6">No hay registros con esos filtros.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($registros->hasPages())<div class="px-3 py-2 border-t border-[var(--c-border)]">{{ $registros->links() }}</div>@endif
    </div>
</div>
