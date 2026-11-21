<div>
    <div class="flex flex-wrap items-end justify-between gap-2 mb-3">
        <div>
            <h1 class="page-title">Vehículos</h1>
            <div class="page-sub">Fichas de vehículos y etiquetas QR</div>
        </div>
        <a href="{{ route('vehiculos.create') }}" wire:navigate class="btn btn-primary">+ Nuevo vehículo</a>
    </div>

    <div class="panel">
        <div class="panel-head">
            <input type="search" class="inp max-w-sm" wire:model.live.debounce.300ms="buscar" placeholder="Buscar por placa, cédula/RUC, propietario o modelo">
            <span class="muted text-[12px] font-normal">{{ $vehiculos->total() }} registros</span>
        </div>
        <div class="tbl-wrap">
            <table class="tbl">
                <thead><tr><th>Placa</th><th>Marca / Modelo</th><th>Año</th><th>Color</th><th>Combustible</th><th>Propietario</th><th class="num">Km actual</th><th></th></tr></thead>
                <tbody>
                @forelse ($vehiculos as $v)
                    <tr wire:key="veh-{{ $v->id }}">
                        <td><a href="{{ route('vehiculos.show', $v) }}" wire:navigate class="link placa">{{ $v->placa_formateada }}</a></td>
                        <td>{{ $v->modelo->marca->nombre }} {{ $v->modelo->nombre }}</td>
                        <td>{{ $v->anio }}</td>
                        <td>{{ $v->color }}</td>
                        <td>{{ \App\Models\Vehiculo::COMBUSTIBLES[$v->tipo_combustible] }}</td>
                        <td>{{ $v->cliente->nombres }} <span class="muted text-[12px]">{{ $v->cliente->numero_documento }}</span></td>
                        <td class="num">{{ $v->kilometraje_actual ? number_format($v->kilometraje_actual, 0, ',', '.') : '—' }}</td>
                        <td class="text-right whitespace-nowrap">
                            <a href="{{ route('vehiculos.show', $v) }}" wire:navigate class="btn btn-sm">Ficha</a>
                            <a href="{{ route('vehiculos.etiqueta', $v) }}" target="_blank" class="btn btn-sm">Etiqueta QR</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center muted py-6">Sin vehículos.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($vehiculos->hasPages())<div class="px-3 py-2 border-t border-[var(--c-border)]">{{ $vehiculos->links() }}</div>@endif
    </div>
</div>
