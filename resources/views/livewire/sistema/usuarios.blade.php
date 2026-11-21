@php use App\Models\Empleado; @endphp
<div>
    <div class="flex flex-wrap items-end justify-between gap-2 mb-3">
        <div>
            <h1 class="page-title">Usuarios y roles</h1>
            <div class="page-sub">Cuentas del panel: el usuario es la cédula. El rol (Spatie) define el acceso; el cargo del empleado es solo descriptivo</div>
        </div>
        <button class="btn btn-primary" wire:click="nuevo">+ Nuevo usuario</button>
    </div>

    @if ($claveTemporal)
        <div class="alert alert-info mb-3 flex flex-wrap items-center justify-between gap-2 anim-aparece">
            <span>Contraseña temporal de <strong>{{ $claveDe }}</strong>: <code class="placa">{{ $claveTemporal }}</code> — entréguela en persona; no se volverá a mostrar.</span>
            <button class="btn btn-sm" wire:click="$set('claveTemporal', null)">Listo</button>
        </div>
    @endif

    <div class="panel">
        <div class="panel-head justify-start">
            <input class="inp w-64" type="search" placeholder="Buscar por nombre o cédula…" wire:model.live.debounce.300ms="buscar" aria-label="Buscar">
        </div>
        <div class="tbl-wrap relative">
            <div wire:loading.delay wire:target="buscar,gotoPage,nextPage,previousPage" class="absolute inset-0 bg-white/60 z-[1] p-3 space-y-2" aria-hidden="true">
                @for ($i = 0; $i < 4; $i++)<div class="skel skel-pulso" style="width: {{ 85 - $i * 9 }}%"></div>@endfor
            </div>
            <table class="tbl">
                <thead><tr><th>Nombre</th><th>Usuario (cédula)</th><th>Correo</th><th>Rol</th><th>Empleado vinculado</th><th>Acceso</th><th></th></tr></thead>
                <tbody>
                @forelse ($usuarios as $u)
                    <tr wire:key="usr-{{ $u->id }}" @class(['muted' => $u->bloqueado_at])>
                        <td class="font-medium">{{ $u->name }} @if ($u->id === auth()->id())<span class="tag-soon">Usted</span>@endif</td>
                        <td>{{ $u->cedula ?? '—' }}</td>
                        <td>{{ $u->email ?? '—' }}</td>
                        <td>{{ $u->roles->pluck('name')->join(', ') ?: 'sin rol' }}</td>
                        <td>{{ $u->empleado ? $u->empleado->apellidos.', '.$u->empleado->nombres.' ('.(Empleado::CARGOS[$u->empleado->cargo] ?? $u->empleado->cargo).')' : '—' }}</td>
                        <td>{!! $u->bloqueado_at ? '<span class="badge st-cancelado">Bloqueado</span>' : '<span class="badge st-listo_entrega">Habilitado</span>' !!}</td>
                        <td class="text-right whitespace-nowrap">
                            <button class="btn btn-sm" wire:click="editar({{ $u->id }})">Editar</button>
                            <button class="btn btn-sm" wire:click="pedirConfirmacion({{ $u->id }}, 'restablecer')">Restablecer contraseña</button>
                            @if ($u->bloqueado_at)
                                <button class="btn btn-sm" wire:click="pedirConfirmacion({{ $u->id }}, 'desbloquear')">Desbloquear</button>
                            @elseif ($u->id !== auth()->id())
                                <button class="btn btn-sm btn-danger" wire:click="pedirConfirmacion({{ $u->id }}, 'bloquear')">Bloquear</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center muted py-6">Sin usuarios.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($usuarios->hasPages())<div class="px-3 py-2 border-t border-[var(--c-border)]">{{ $usuarios->links() }}</div>@endif
    </div>

    @if ($modal)
        <div class="modal-back" wire:key="modal-usuario">
            <form class="modal max-w-[560px]" wire:submit="guardar">
                <div class="modal-head"><span>{{ $editandoId ? 'Editar usuario' : 'Nuevo usuario' }}</span><button type="button" wire:click="$set('modal', false)">✕</button></div>
                <div class="p-4 grid sm:grid-cols-2 gap-3">
                    <x-campo label="Nombre" :req="true" error="form.name" class="sm:col-span-2"><input class="inp" wire:model="form.name"></x-campo>
                    <x-campo label="Cédula (usuario)" :req="true" error="form.cedula"><input class="inp" wire:model="form.cedula" inputmode="numeric" maxlength="10"></x-campo>
                    <x-campo label="Correo (recuperar contraseña)" error="form.email"><input class="inp" type="email" wire:model="form.email"></x-campo>
                    <x-campo label="Rol" :req="true" error="form.rol">
                        <select class="inp" wire:model="form.rol">
                            <option value="">—</option>
                            @foreach ($this->roles as $r)<option value="{{ $r }}" wire:key="rol-{{ $r }}">{{ $r }}</option>@endforeach
                        </select>
                    </x-campo>
                    <x-campo label="Empleado vinculado" error="form.empleado_id">
                        <select class="inp" wire:model="form.empleado_id">
                            <option value="">Sin empleado</option>
                            @foreach ($this->empleados as $e)<option value="{{ $e->id }}" wire:key="emp-{{ $e->id }}">{{ $e->apellidos }}, {{ $e->nombres }} — {{ Empleado::CARGOS[$e->cargo] ?? $e->cargo }}</option>@endforeach
                        </select>
                    </x-campo>
                    <x-campo :label="$editandoId ? 'Nueva contraseña (vacío = no cambia)' : 'Contraseña'" :req="! $editandoId" error="form.password" class="sm:col-span-2">
                        <input class="inp" type="password" wire:model="form.password" autocomplete="new-password">
                    </x-campo>
                </div>
                <div class="modal-foot">
                    <span class="muted text-[12px] mr-auto" wire:dirty wire:target="form">Cambios sin guardar</span>
                    <button type="button" class="btn" wire:click="$set('modal', false)">Cancelar</button>
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="guardar">
                        <span wire:loading.remove wire:target="guardar">Guardar</span><span wire:loading wire:target="guardar">Guardando…</span>
                    </button>
                </div>
            </form>
        </div>
    @endif

    @if ($confirmando)
        <div class="modal-back" wire:key="modal-confirmar">
            <div class="modal max-w-[460px]" role="dialog" aria-modal="true">
                <div class="modal-head">
                    <span>{{ ['bloquear' => 'Bloquear acceso', 'desbloquear' => 'Desbloquear acceso', 'restablecer' => 'Restablecer contraseña'][$confirmarAccion] }}</span>
                    <button type="button" wire:click="cancelarConfirmacion">✕</button>
                </div>
                <div class="p-4">
                    @if ($confirmarAccion === 'bloquear')
                        <p><strong>{{ $confirmando->name }}</strong> no podrá iniciar sesión y se cerrarán sus sesiones abiertas.</p>
                    @elseif ($confirmarAccion === 'desbloquear')
                        <p><strong>{{ $confirmando->name }}</strong> podrá volver a iniciar sesión con su contraseña actual.</p>
                    @else
                        <p>Se generará una contraseña temporal para <strong>{{ $confirmando->name }}</strong> y se cerrará su "Recordarme". La contraseña se muestra una sola vez.</p>
                    @endif
                </div>
                <div class="modal-foot">
                    <button type="button" class="btn" wire:click="cancelarConfirmacion">Cancelar</button>
                    <button type="button" class="btn btn-primary" wire:click="confirmar" wire:loading.attr="disabled" wire:target="confirmar">
                        <span wire:loading.remove wire:target="confirmar">Confirmar</span><span wire:loading wire:target="confirmar">Procesando…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
