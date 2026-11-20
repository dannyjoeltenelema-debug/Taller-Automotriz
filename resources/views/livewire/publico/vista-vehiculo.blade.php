@php
    use App\Models\Cita;
    use App\Support\Mascara;
@endphp
<div class="space-y-3">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
            <a href="{{ route('home') }}" wire:navigate class="link text-[12.5px]">‹ Nueva consulta</a>
            <h1 class="page-title"><span class="placa">{{ $v->placa_formateada }}</span> · {{ $descripcion }}</h1>
        </div>
        @if ($whatsapp)
            <a href="{{ $whatsapp }}" target="_blank" rel="noopener" class="btn" style="border-color:#15803D;color:#15803D">Escribir por WhatsApp</a>
        @endif
    </div>

    @if ($enCurso)
        <div class="alert alert-info">
            Su vehículo se encuentra en el taller (orden {{ $enCurso->codigo }}): <x-estado :estado="$enCurso->estado" />
        </div>
    @endif

    <section class="panel">
        <div class="panel-head">Datos del vehículo</div>
        <table class="tbl">
            <tr><td class="muted w-40">Placa</td><td class="placa">{{ $v->placa_formateada }}</td></tr>
            <tr><td class="muted">Marca / Modelo</td><td>{{ $v->modelo->marca->nombre }} {{ $v->modelo->nombre }}</td></tr>
            <tr><td class="muted">Año</td><td>{{ $v->anio }}</td></tr>
            <tr><td class="muted">Color</td><td>{{ $v->color }}</td></tr>
            <tr><td class="muted">VIN / Chasis</td><td>{{ Mascara::vin($v->vin) }}</td></tr>
            <tr><td class="muted">Propietario</td><td>{{ Mascara::nombre($v->cliente->nombres) }} · {{ Mascara::documento($v->cliente->numero_documento) }}</td></tr>
            <tr><td class="muted">Último km registrado</td><td class="font-semibold">{{ $v->kilometraje_actual ? number_format($v->kilometraje_actual, 0, ',', '.').' km' : '—' }}</td></tr>
            <tr><td class="muted">Próximo mantenimiento</td><td class="font-semibold" style="color:var(--c-accent)">{{ $proximoKm ? number_format($proximoKm, 0, ',', '.').' km' : '—' }}</td></tr>
        </table>
    </section>

    <section class="panel">
        <div class="panel-head">Historial de mantenimientos</div>
        <div class="tbl-wrap">
            <table class="tbl">
                <thead><tr><th>Fecha</th><th class="num">Km</th><th>Servicios realizados</th></tr></thead>
                <tbody>
                @forelse ($historial as $o)
                    <tr>
                        <td class="whitespace-nowrap">{{ $o->fecha_ingreso->format('d/m/Y') }}</td>
                        <td class="num">{{ number_format($o->kilometraje_ingreso, 0, ',', '.') }}</td>
                        <td>{{ $o->items->pluck('descripcion')->join(', ') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="muted text-center py-3">Aún no hay mantenimientos registrados.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel" id="agendar">
        <div class="panel-head">Agendar una cita</div>
        @if ($citaEnviada)
            <div class="panel-body">
                <div class="alert alert-ok">
                    Solicitud registrada para el {{ \Carbon\Carbon::parse($fecha_deseada)->format('d/m/Y') }}, franja {{ Cita::etiquetaFranja($hora_inicio) }}.
                    Le contactaremos para confirmar.
                </div>
            </div>
        @else
            <form class="panel-body grid sm:grid-cols-2 gap-3" wire:submit="agendar">
                <x-campo label="Fecha deseada" :req="true" error="fecha_deseada">
                    <input type="date" class="inp" wire:model="fecha_deseada" min="{{ now()->toDateString() }}" max="{{ now()->addDays(60)->toDateString() }}">
                </x-campo>
                <x-campo label="Franja horaria" :req="true" error="hora_inicio">
                    <select class="inp" wire:model="hora_inicio">
                        @foreach (Cita::FRANJAS as $f)<option value="{{ $f }}">{{ Cita::etiquetaFranja($f) }}</option>@endforeach
                    </select>
                </x-campo>
                @if ($sedes->count() > 1)
                    <x-campo label="Sede" :req="true" error="sede_id" class="sm:col-span-2">
                        <select class="inp" wire:model="sede_id">@foreach ($sedes as $s)<option value="{{ $s->id }}">{{ $s->nombre }} — {{ $s->direccion }}</option>@endforeach</select>
                    </x-campo>
                @endif
                <x-campo label="Servicio / motivo" :req="true" error="motivo" class="sm:col-span-2">
                    <input class="inp" list="dl-servicios" wire:model="motivo" placeholder="Ej. Cambio de aceite">
                    <datalist id="dl-servicios">@foreach ($servicios as $s)<option value="{{ $s }}">@endforeach</datalist>
                </x-campo>
                <x-campo label="Su nombre" :req="true" error="cliente_nombre"><input class="inp" wire:model="cliente_nombre" autocomplete="name"></x-campo>
                <x-campo label="Teléfono de contacto" :req="true" error="cliente_telefono"><input class="inp" wire:model="cliente_telefono" inputmode="tel" autocomplete="tel" placeholder="09XXXXXXXX"></x-campo>
                <x-campo label="Observación" error="observacion" class="sm:col-span-2"><textarea class="inp" rows="2" wire:model="observacion"></textarea></x-campo>
                <div class="sm:col-span-2 flex justify-end">
                    <button class="btn btn-primary" wire:loading.attr="disabled">Solicitar cita</button>
                </div>
            </form>
        @endif
    </section>
</div>
