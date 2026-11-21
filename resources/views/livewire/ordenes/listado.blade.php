<div x-data="{ aviso: '' }" x-on:aviso.window="aviso = $event.detail.mensaje; setTimeout(() => aviso = '', 3500)">
    <div class="flex flex-wrap items-end justify-between gap-2 mb-3">
        <div>
            <h1 class="page-title">Órdenes de trabajo</h1>
            <div class="page-sub">Recepción, seguimiento y entrega de vehículos</div>
        </div>
        <a href="{{ route('ordenes.create') }}" wire:navigate class="btn btn-primary">+ Nueva Orden de Trabajo</a>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-2 mb-3">
        <div class="kpi"><div class="kpi-label">OT activas</div><div class="kpi-value">{{ $kpi['activas'] }}</div></div>
        <div class="kpi"><div class="kpi-label">En proceso</div><div class="kpi-value">{{ $kpi['en_proceso'] }}</div></div>
        <div class="kpi"><div class="kpi-label">Listas para entrega</div><div class="kpi-value">{{ $kpi['listo_entrega'] }}</div></div>
        <a href="{{ route('citas.index') }}" wire:navigate class="kpi block hover:bg-[var(--c-row-hover)]"><div class="kpi-label">Citas pendientes</div><div class="kpi-value">{{ $kpi['citas'] }}</div></a>
    </div>

    <div x-show="aviso" x-cloak x-text="aviso" class="alert alert-ok mb-2"></div>

    <div class="panel">
        <div class="panel-head">
            <div class="flex flex-wrap items-center gap-2 w-full">
                <input type="search" wire:model.live.debounce.300ms="buscar" class="inp max-w-sm"
                       placeholder="Buscar por placa, cédula/RUC, nombre o código OT">
                <select wire:model.live="estado" class="inp w-auto">
                    <option value="activas">Activas (no entregadas)</option>
                    @foreach (\App\Models\OrdenTrabajo::ESTADOS as $k => $v)
                        <option value="{{ $k }}">{{ $v }}</option>
                    @endforeach
                    <option value="todas">Todas</option>
                </select>
                <span wire:loading class="muted text-[12px]">Buscando…</span>
            </div>
        </div>
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                <tr>
                    <th>Código OT</th>
                    <th>Ingreso</th>
                    <th>Placa / Vehículo</th>
                    <th>Cliente</th>
                    <th>Técnico</th>
                    <th class="num">Total</th>
                    <th>Estado</th>
                    <th>Cobro</th>
                    <th class="text-right">Acciones</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($ordenes as $o)
                    <tr wire:key="ot-{{ $o->id }}">
                        <td><a href="{{ route('ordenes.edit', $o) }}" wire:navigate class="link font-semibold">{{ $o->codigo }}</a></td>
                        <td class="whitespace-nowrap">{{ $o->fecha_ingreso->format('d/m/Y H:i') }}</td>
                        <td>
                            <span class="placa">{{ $o->vehiculo->placa_formateada }}</span>
                            <div class="muted text-[12px]">{{ $o->vehiculo->modelo->marca->nombre }} {{ $o->vehiculo->modelo->nombre }}</div>
                        </td>
                        <td>
                            {{ $o->cliente->nombres }}
                            <div class="muted text-[12px]">{{ $o->cliente->numero_documento }}</div>
                        </td>
                        <td>{{ $o->tecnico?->nombre_completo ?? '—' }}</td>
                        <td class="num">{{ \App\Support\Dinero::mostrar($o->total) }}</td>
                        <td>
                            <select class="inp h-[26px] py-0 text-[12px] w-[150px] badge st-{{ $o->estado }}"
                                    wire:change="cambiarEstado({{ $o->id }}, $event.target.value)">
                                @foreach (\App\Models\OrdenTrabajo::ESTADOS as $k => $v)
                                    <option value="{{ $k }}" @selected($o->estado === $k)>{{ $v }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <x-estado :estado="$o->estado_cobro" :labels="['pendiente' => 'Pendiente', 'pagado' => 'Pagado']" />
                            @if ($o->forma_cobro)<div class="muted text-[11.5px]">{{ \App\Models\OrdenTrabajo::FORMAS_COBRO[$o->forma_cobro] }}</div>@endif
                        </td>
                        <td class="text-right whitespace-nowrap">
                            <a href="{{ route('ordenes.edit', $o) }}" wire:navigate class="btn btn-sm">Ver / Editar</a>
                            <a href="{{ route('vehiculos.etiqueta', $o->vehiculo_id) }}" target="_blank" class="btn btn-sm">Etiqueta QR</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center muted py-6">No hay órdenes que coincidan con la búsqueda.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($ordenes->hasPages())
            <div class="px-3 py-2 border-t border-[var(--c-border)]">{{ $ordenes->links() }}</div>
        @endif
    </div>
</div>
