<div>
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

    <div class="panel">
        <div class="panel-head">
            <div class="flex flex-wrap items-center gap-2 w-full">
                <input type="search" wire:model.live.debounce.300ms="buscar" class="inp max-w-sm"
                       placeholder="Buscar por placa, cédula/RUC, nombre o código OT">
                <select wire:model.live="estado" class="inp w-auto">
                    <option value="activas">Activas (no entregadas)</option>
                    @foreach (\App\Models\OrdenTrabajo::ESTADOS as $k => $v)
                        <option value="{{ $k }}" wire:key="opt-{{ $k }}">{{ $v }}</option>
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
                            @php $opciones = $o->estadosElegibles(auth()->user()); @endphp
                            @if (count($opciones) > 1)
                                {{-- Solo el estado actual y las transiciones permitidas (config/taller.php). --}}
                                <select class="inp h-[26px] py-0 text-[12px] w-[150px] badge st-{{ $o->estado }}"
                                        wire:key="est-{{ $o->id }}-{{ $o->estado }}-{{ $version }}"
                                        wire:change="cambiarEstado({{ $o->id }}, $event.target.value)" aria-label="Estado de {{ $o->codigo }}">
                                    @foreach ($opciones as $k)
                                        <option value="{{ $k }}" wire:key="est-{{ $o->id }}-{{ $k }}" @selected($o->estado === $k)>{{ \App\Models\OrdenTrabajo::ESTADOS[$k] }}</option>
                                    @endforeach
                                </select>
                            @else
                                <x-estado :estado="$o->estado" />
                            @endif
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

    @if ($porEntregar)
        <div class="modal-back" wire:key="modal-entrega">
            <div class="modal max-w-[460px]" role="dialog" aria-modal="true">
                <div class="modal-head"><span>Entregar con cobro pendiente</span><button type="button" wire:click="cancelarEntrega">✕</button></div>
                <div class="p-4 space-y-2">
                    <p>La orden <strong>{{ $porEntregar->codigo }}</strong> tiene el cobro pendiente ({{ \App\Support\Dinero::mostrar($porEntregar->total) }}).</p>
                    <p class="muted text-[12.5px]">Puede entregarla igual; el cobro seguirá pendiente hasta que se registre en la orden.</p>
                </div>
                <div class="modal-foot">
                    <button type="button" class="btn" wire:click="cancelarEntrega">Cancelar</button>
                    <button type="button" class="btn btn-primary" wire:click="confirmarEntrega" wire:loading.attr="disabled" wire:target="confirmarEntrega">Entregar de todas formas</button>
                </div>
            </div>
        </div>
    @endif
</div>
