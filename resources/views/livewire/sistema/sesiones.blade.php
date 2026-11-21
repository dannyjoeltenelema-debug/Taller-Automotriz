<div>
    <div class="mb-3">
        <h1 class="page-title">Sesiones activas</h1>
        <div class="page-sub">Quién tiene el panel abierto ahora. Cerrar una sesión obliga a esa persona a volver a iniciar sesión</div>
    </div>

    @if (! $disponible)
        <div class="alert alert-info">
            Las sesiones no se guardan en base de datos (SESSION_DRIVER = <code>{{ config('session.driver') }}</code>), así que no se pueden listar ni cerrar desde aquí.
            No se cambió el driver: si se necesita esta función, configure <code>SESSION_DRIVER=database</code> en el .env.
        </div>
    @else
        <div class="panel tbl-wrap">
            <table class="tbl">
                <thead><tr><th>Usuario</th><th>IP</th><th>Navegador</th><th>Última actividad</th><th></th></tr></thead>
                <tbody>
                @forelse ($sesiones as $s)
                    <tr wire:key="ses-{{ $s->id }}">
                        <td>{{ $s->name ?? 'Visitante (sin login)' }} @if ($s->cedula)<span class="muted">({{ $s->cedula }})</span>@endif
                            @if ($s->id === $actual)<span class="tag-soon">Esta sesión</span>@endif</td>
                        <td>{{ $s->ip_address ?? '—' }}</td>
                        <td class="muted" title="{{ $s->user_agent }}">{{ Str::limit($s->user_agent, 60) }}</td>
                        <td class="whitespace-nowrap">{{ $s->ultima->format('d/m/Y H:i') }} <span class="muted">({{ $s->ultima->diffForHumans() }})</span></td>
                        <td class="text-right">
                            @if ($s->id !== $actual)
                                <button class="btn btn-sm btn-danger" wire:click="confirmarCierre('{{ $s->id }}')">Cerrar sesión</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center muted py-6">No hay sesiones activas.</td></tr>
                @endforelse
                </tbody>
            </table>
            @if ($sesiones->hasPages())<div class="px-3 py-2 border-t border-[var(--c-border)]">{{ $sesiones->links() }}</div>@endif
        </div>
    @endif

    @if ($cerrandoId)
        <div class="modal-back" wire:key="modal-cerrar-sesion">
            <div class="modal max-w-[440px]" role="dialog" aria-modal="true">
                <div class="modal-head"><span>Cerrar sesión</span><button type="button" wire:click="$set('cerrandoId', null)">✕</button></div>
                <div class="p-4"><p>La persona tendrá que volver a iniciar sesión. Si usó "Recordarme", podrá entrar de nuevo sin contraseña; para impedirlo, bloquee su usuario en Usuarios y roles.</p></div>
                <div class="modal-foot">
                    <button type="button" class="btn" wire:click="$set('cerrandoId', null)">Cancelar</button>
                    <button type="button" class="btn btn-primary" wire:click="cerrar" wire:loading.attr="disabled" wire:target="cerrar">Cerrar sesión</button>
                </div>
            </div>
        </div>
    @endif
</div>
