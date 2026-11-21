@php use App\Models\Empleado; @endphp
<div>
    <div class="flex flex-wrap items-end justify-between gap-2 mb-3">
        <div>
            <h1 class="page-title">Empleados</h1>
            <div class="page-sub">Técnicos y mecánicos no tienen acceso al sistema; administradores/asesores pueden tener usuario vinculado</div>
        </div>
        <button class="btn btn-primary" wire:click="nuevo">+ Nuevo empleado</button>
    </div>

    @if ($aviso)<div class="alert alert-ok mb-3">{{ $aviso }}</div>@endif

    <div class="panel">
        <div class="panel-head">
            <select class="inp w-auto" wire:model.live="filtroCargo">
                <option value="">Todos los cargos</option>
                @foreach (Empleado::CARGOS as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
            </select>
        </div>
        <div class="tbl-wrap">
            <table class="tbl">
                <thead><tr><th>Apellidos y nombres</th><th>Cédula</th><th>Cargo</th><th>Especialidad</th><th>Sede</th><th>Teléfono</th><th>Acceso al sistema</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                @foreach ($empleados as $e)
                    <tr wire:key="emp-{{ $e->id }}" @class(['muted' => ! $e->activo])>
                        <td class="font-medium">{{ $e->apellidos }}, {{ $e->nombres }}</td>
                        <td>{{ $e->cedula }}</td>
                        <td>{{ Empleado::CARGOS[$e->cargo] }}</td>
                        <td>{{ $e->especialidad ?? '—' }}</td>
                        <td>{{ $e->sede->nombre }}</td>
                        <td>{{ $e->telefono ?? '—' }}</td>
                        <td>@if ($e->user){{ $e->user->cedula }} <span class="muted">({{ $e->user->roles->pluck('name')->join(', ') ?: 'sin rol' }})</span>@else<span class="muted">Sin usuario</span>@endif</td>
                        <td>{!! $e->activo ? '<span class="badge st-listo_entrega">Activo</span>' : '<span class="badge st-entregado">Inactivo</span>' !!}</td>
                        <td class="text-right"><button class="btn btn-sm" wire:click="editar({{ $e->id }})">Editar</button></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @if ($empleados->hasPages())<div class="px-3 py-2 border-t border-[var(--c-border)]">{{ $empleados->links() }}</div>@endif
    </div>

    @if ($modal)
        <div class="modal-back">
            <form class="modal max-w-[640px]" wire:submit="guardar">
                <div class="modal-head"><span>{{ $editandoId ? 'Editar empleado' : 'Nuevo empleado' }}</span><button type="button" wire:click="$set('modal', false)">✕</button></div>
                <div class="p-4 grid sm:grid-cols-2 gap-3">
                    <x-campo label="Nombres" :req="true" error="form.nombres"><input class="inp" wire:model="form.nombres"></x-campo>
                    <x-campo label="Apellidos" :req="true" error="form.apellidos"><input class="inp" wire:model="form.apellidos"></x-campo>
                    <x-campo label="Cédula" :req="true" error="form.cedula"><input class="inp" wire:model="form.cedula" maxlength="10" inputmode="numeric"></x-campo>
                    <x-campo label="Teléfono" error="form.telefono"><input class="inp" wire:model="form.telefono"></x-campo>
                    <x-campo label="Cargo" :req="true" error="form.cargo">
                        <select class="inp" wire:model.live="form.cargo">@foreach (Empleado::CARGOS as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select>
                    </x-campo>
                    <x-campo label="Sede" :req="true" error="form.sede_id">
                        <select class="inp" wire:model="form.sede_id">@foreach ($sedes as $s)<option value="{{ $s->id }}" wire:key="sede-{{ $s->id }}">{{ $s->nombre }}</option>@endforeach</select>
                    </x-campo>
                    <x-campo label="Especialidad" error="form.especialidad"><input class="inp" wire:model="form.especialidad" placeholder="Ej. Frenos, electricidad…"></x-campo>
                    <label class="flex items-center gap-2 mt-5"><input type="checkbox" wire:model="form.activo"> Empleado activo</label>

                    @if (in_array($form['cargo'] ?? '', ['admin', 'asesor']))
                        <div class="sm:col-span-2 border-t border-[var(--c-border)] pt-3">
                            @if ($empleadoEditando?->user)
                                <div class="muted">Usuario vinculado: <strong>{{ $empleadoEditando->user->cedula }}</strong></div>
                            @else
                                <label class="flex items-center gap-2 font-semibold"><input type="checkbox" wire:model.live="crearAcceso"> Crear usuario para acceder al sistema</label>
                                @if ($crearAcceso)
                                    <div class="grid sm:grid-cols-3 gap-3 mt-2">
                                        <div class="sm:col-span-3 muted">El usuario para iniciar sesión será la cédula del empleado.</div>
                                        <x-campo label="Correo (opcional, para recuperar contraseña)" error="email"><input type="email" class="inp" wire:model="email" autocomplete="off"></x-campo>
                                        <x-campo label="Contraseña" :req="true" error="password"><input type="password" class="inp" wire:model="password" autocomplete="new-password"></x-campo>
                                        <x-campo label="Rol" error="rol">
                                            <select class="inp" wire:model="rol">
                                                <option value="admin">admin</option>
                                                @role('superadmin')<option value="superadmin">superadmin</option>@endrole
                                            </select>
                                        </x-campo>
                                    </div>
                                @endif
                            @endif
                        </div>
                    @else
                        <div class="sm:col-span-2 muted text-[12px]">Técnicos y mecánicos se registran sin usuario (no inician sesión).</div>
                    @endif
                </div>
                <div class="modal-foot">
                    <button type="button" class="btn" wire:click="$set('modal', false)">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    @endif
</div>
