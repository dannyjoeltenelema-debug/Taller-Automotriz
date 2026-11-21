@php($v = $vehiculo)
<div>
    <div class="flex flex-wrap items-end justify-between gap-2 mb-3">
        <div>
            <div class="page-sub"><a href="{{ route('vehiculos.index') }}" wire:navigate class="link">Vehículos</a> /</div>
            <h1 class="page-title"><span class="placa">{{ $v->placa_formateada }}</span> · {{ $v->modelo->marca->nombre }} {{ $v->modelo->nombre }} {{ $v->anio }}</h1>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('vehiculos.edit', $v) }}" wire:navigate class="btn">Editar ficha</a>
            <a href="{{ route('vehiculos.etiqueta', $v) }}" target="_blank" class="btn">Ver / Imprimir etiqueta QR</a>
            <a href="{{ route('ordenes.create', ['vehiculo' => $v->id]) }}" wire:navigate class="btn btn-primary">+ Nueva OT</a>
        </div>
    </div>

    <div class="grid md:grid-cols-2 gap-3 mb-3">
        <section class="panel">
            <div class="panel-head">Datos del vehículo</div>
            <table class="tbl">
                <tr><td class="muted w-44">Placa</td><td class="placa">{{ $v->placa_formateada }}</td></tr>
                <tr><td class="muted">Marca / Modelo</td><td>{{ $v->modelo->marca->nombre }} {{ $v->modelo->nombre }}</td></tr>
                <tr><td class="muted">Año</td><td>{{ $v->anio }}</td></tr>
                <tr><td class="muted">Color</td><td>{{ $v->color }}</td></tr>
                <tr><td class="muted">VIN / Chasis</td><td>{{ $v->vin ?? '—' }}</td></tr>
                <tr><td class="muted">N.º motor</td><td>{{ $v->numero_motor ?? '—' }}</td></tr>
                <tr><td class="muted">Combustible / Transmisión</td><td>{{ \App\Models\Vehiculo::COMBUSTIBLES[$v->tipo_combustible] }} · {{ \App\Models\Vehiculo::TRANSMISIONES[$v->transmision] }}</td></tr>
                <tr><td class="muted">Estado</td><td>{{ $v->activo ? 'Activo' : 'Inactivo' }}</td></tr>
            </table>
        </section>
        <section class="panel">
            <div class="panel-head">Mantenimiento y propietario</div>
            <table class="tbl">
                <tr><td class="muted w-44">Km actual</td><td class="font-semibold">{{ $v->kilometraje_actual ? number_format($v->kilometraje_actual, 0, ',', '.').' km' : '—' }}</td></tr>
                <tr><td class="muted">Próximo mantenimiento</td><td class="font-semibold">{{ $proximoKm ? number_format($proximoKm, 0, ',', '.').' km' : '—' }}</td></tr>
                <tr><td class="muted">Intervalo</td><td>{{ number_format($v->intervalo_mantenimiento_km, 0, ',', '.') }} km</td></tr>
                <tr><td class="muted">Propietario</td><td>{{ $v->cliente->nombres }}</td></tr>
                <tr><td class="muted">Cédula / RUC</td><td>{{ $v->cliente->numero_documento }}</td></tr>
                <tr><td class="muted">Teléfono</td><td>{{ $v->cliente->telefono }}</td></tr>
                <tr><td class="muted">Convenio</td><td>{{ $v->cliente->empresaColaboradora?->nombre ?? 'No aplica' }}</td></tr>
                <tr><td class="muted">Enlace público (QR)</td><td><a class="link text-[12px] break-all" target="_blank" href="{{ route('publico.vehiculo', $v->qr_token) }}">{{ route('publico.vehiculo', $v->qr_token) }}</a></td></tr>
            </table>
        </section>
    </div>

    @if ($activas->isNotEmpty())
        <section class="panel mb-3">
            <div class="panel-head">Órdenes en curso</div>
            <table class="tbl">
                @foreach ($activas as $o)
                    <tr wire:key="act-{{ $o->id }}"><td><a href="{{ route('ordenes.edit', $o) }}" wire:navigate class="link font-semibold">{{ $o->codigo }}</a></td><td>{{ $o->fecha_ingreso->format('d/m/Y') }}</td><td><x-estado :estado="$o->estado" /></td><td class="num">{{ \App\Support\Dinero::mostrar($o->total) }}</td></tr>
                @endforeach
            </table>
        </section>
    @endif

    <section class="panel">
        <div class="panel-head">Historial de mantenimientos (OT entregadas)</div>
        <div class="tbl-wrap">
            <table class="tbl">
                <thead><tr><th>OT</th><th>Fecha</th><th class="num">Km</th><th>Servicios</th><th>Técnico</th><th class="num">Total</th></tr></thead>
                <tbody>
                @forelse ($historial as $o)
                    <tr wire:key="hist-{{ $o->id }}">
                        <td><a href="{{ route('ordenes.edit', $o) }}" wire:navigate class="link">{{ $o->codigo }}</a></td>
                        <td>{{ $o->fecha_ingreso->format('d/m/Y') }}</td>
                        <td class="num">{{ number_format($o->kilometraje_ingreso, 0, ',', '.') }}</td>
                        <td>{{ $o->items->pluck('descripcion')->join(', ') }}</td>
                        <td>{{ $o->tecnico?->nombre_completo ?? '—' }}</td>
                        <td class="num">{{ \App\Support\Dinero::mostrar($o->total) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted text-center py-4">Sin mantenimientos entregados.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
