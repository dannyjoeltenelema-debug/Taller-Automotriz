@php use App\Models\Cita; @endphp
<div>
    <div class="flex flex-wrap items-end justify-between gap-2 mb-3">
        <div>
            <h1 class="page-title">Citas / Solicitudes</h1>
            <div class="page-sub">Solicitudes desde el portal (QR) y citas registradas en recepción</div>
        </div>
        <button class="btn btn-primary" wire:click="nueva">+ Nueva cita (recepción)</button>
    </div>

    @if ($aviso)<div class="alert alert-ok mb-3">{{ $aviso }}</div>@endif

    <div class="panel">
        <div class="panel-head">
            <div class="flex flex-wrap gap-2 items-center">
                <select class="inp w-auto" wire:model.live="estado">
                    <option value="abiertas">Pendientes y confirmadas</option>
                    @foreach (Cita::ESTADOS as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                    <option value="todas">Todas</option>
                </select>
                <input type="date" class="inp w-auto" wire:model.live="fecha" title="Filtrar por fecha deseada">
                @if ($fecha)<button class="btn btn-sm" wire:click="$set('fecha', '')">Quitar fecha</button>@endif
            </div>
        </div>
        <div class="tbl-wrap">
            <table class="tbl">
                <thead><tr><th>Solicitada</th><th>Fecha / franja deseada</th><th>Placa / Vehículo</th><th>Cliente</th><th>Motivo</th><th>Origen</th><th>Estado</th><th class="text-right">Acciones</th></tr></thead>
                <tbody>
                @forelse ($citas as $c)
                    <tr wire:key="cita-{{ $c->id }}">
                        <td class="whitespace-nowrap muted">{{ $c->created_at->format('d/m/Y H:i') }}</td>
                        <td class="whitespace-nowrap"><strong>{{ $c->fecha_deseada->format('d/m/Y') }}</strong><div class="text-[12px]">{{ $c->franja }} · {{ $c->sede->nombre }}</div></td>
                        <td>
                            <span class="placa">{{ \App\Support\Placa::formatear($c->placa) }}</span>
                            <div class="muted text-[12px]">{{ $c->vehiculo ? $c->vehiculo->modelo->marca->nombre.' '.$c->vehiculo->modelo->nombre : 'No registrado' }}</div>
                        </td>
                        <td>{{ $c->cliente_nombre }}<div class="muted text-[12px]">{{ $c->cliente_telefono }}</div></td>
                        <td>{{ $c->motivo }}@if($c->observacion)<div class="muted text-[12px]">{{ \Illuminate\Support\Str::limit($c->observacion, 60) }}</div>@endif</td>
                        <td>{{ Cita::ORIGENES[$c->origen] }}</td>
                        <td>
                            <x-estado :estado="$c->estado" :labels="Cita::ESTADOS" />
                            @if ($c->orden)<div><a href="{{ route('ordenes.edit', $c->orden) }}" wire:navigate class="link text-[12px]">{{ $c->orden->codigo }}</a></div>@endif
                        </td>
                        <td class="text-right whitespace-nowrap">
                            @if ($c->estado === 'pendiente')
                                <button class="btn btn-sm" wire:click="confirmar({{ $c->id }})">Confirmar</button>
                            @endif
                            @if (in_array($c->estado, ['pendiente', 'confirmada']))
                                <button class="btn btn-sm btn-primary" wire:click="convertir({{ $c->id }})">Convertir en OT</button>
                                <button class="btn btn-sm btn-danger" wire:click="cancelar({{ $c->id }})" wire:confirm="¿Cancelar esta cita?">Cancelar</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center muted py-6">No hay citas con estos filtros.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($citas->hasPages())<div class="px-3 py-2 border-t border-[var(--c-border)]">{{ $citas->links() }}</div>@endif
    </div>

    @if ($modal)
        <div class="modal-back">
            <form class="modal max-w-[600px]" wire:submit="guardar">
                <div class="modal-head"><span>Nueva cita (recepción)</span><button type="button" wire:click="$set('modal', false)">✕</button></div>
                <div class="p-4 grid sm:grid-cols-2 gap-3">
                    <x-campo label="Placa" :req="true" error="form.placa"><input class="inp placa uppercase" wire:model.blur="form.placa" placeholder="PBA-4521"></x-campo>
                    <x-campo label="Sede" :req="true" error="form.sede_id">
                        <select class="inp" wire:model="form.sede_id">@foreach ($sedes as $s)<option value="{{ $s->id }}" wire:key="sede-{{ $s->id }}">{{ $s->nombre }}</option>@endforeach</select>
                    </x-campo>
                    <x-campo label="Nombre del cliente" :req="true" error="form.cliente_nombre"><input class="inp" wire:model="form.cliente_nombre"></x-campo>
                    <x-campo label="Teléfono" :req="true" error="form.cliente_telefono"><input class="inp" wire:model="form.cliente_telefono"></x-campo>
                    <x-campo label="Fecha deseada" :req="true" error="form.fecha_deseada"><input type="date" class="inp" wire:model="form.fecha_deseada" min="{{ now()->toDateString() }}"></x-campo>
                    <x-campo label="Franja horaria" :req="true" error="form.hora_inicio">
                        <select class="inp" wire:model="form.hora_inicio">@foreach (Cita::franjas() as $f)<option value="{{ $f }}">{{ Cita::etiquetaFranja($f) }}</option>@endforeach</select>
                    </x-campo>
                    <x-campo label="Motivo / servicio" :req="true" error="form.motivo" class="sm:col-span-2"><input class="inp" wire:model="form.motivo"></x-campo>
                    <x-campo label="Observación" error="form.observacion" class="sm:col-span-2"><textarea class="inp" rows="2" wire:model="form.observacion"></textarea></x-campo>
                </div>
                <div class="modal-foot">
                    <button type="button" class="btn" wire:click="$set('modal', false)">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Registrar cita</button>
                </div>
            </form>
        </div>
    @endif
</div>
