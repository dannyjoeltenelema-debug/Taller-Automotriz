@php
    use App\Models\OrdenTrabajo;
    use App\Models\OtItem;
    use App\Support\Dinero;
    $pct = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $orden->codigo }}</title>
    <style>
        @page { margin: 14mm 12mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9.5px; color: #1F2937; }
        table { width: 100%; border-collapse: collapse; }
        .enc td { vertical-align: top; }
        .taller { font-size: 14px; font-weight: bold; }
        .muted { color: #6B7280; }
        .codigo { border: 1px solid #1E40AF; color: #1E40AF; padding: 6px 10px; text-align: center; }
        .codigo b { font-size: 14px; display: block; }
        h3 { font-size: 10px; background: #F3F4F6; border: 1px solid #D1D5DB; padding: 3px 6px; margin: 10px 0 0; text-transform: uppercase; }
        .box td { border: 1px solid #D1D5DB; padding: 3px 6px; }
        .box .k { background: #FAFAFB; width: 18%; font-weight: bold; }
        .items th { background: #F3F4F6; border: 1px solid #D1D5DB; padding: 3px 6px; text-align: left; }
        .items td { border: 1px solid #D1D5DB; padding: 3px 6px; }
        .r { text-align: right; }
        .tot td { padding: 2px 6px; }
        .tot .g td { border-top: 1px solid #1F2937; font-weight: bold; font-size: 11px; }
        .firmas td { width: 50%; padding-top: 34px; text-align: center; }
        .firmas span { border-top: 1px solid #1F2937; padding: 2px 30px; }
    </style>
</head>
<body>
<table class="enc">
    <tr>
        <td style="width:62%">
            <div class="taller">{{ $empresa?->razon_social }}</div>
            <div class="muted">RUC {{ $empresa?->ruc }} · {{ $orden->sede->nombre }}<br>{{ $orden->sede->direccion }} · Tel. {{ $orden->sede->telefono ?? $empresa?->telefono }}</div>
        </td>
        <td style="width:24%">
            <div class="codigo">ORDEN DE TRABAJO<b>{{ $orden->codigo }}</b>{{ OrdenTrabajo::ESTADOS[$orden->estado] }}</div>
        </td>
        <td style="width:14%; text-align:right">
            <img src="data:image/svg+xml;base64,{{ $qr }}" width="70" height="70" alt="QR">
        </td>
    </tr>
</table>

<h3>Cliente y vehículo</h3>
<table class="box">
    <tr><td class="k">Cliente</td><td>{{ $orden->cliente->nombres }}</td><td class="k">Cédula/RUC</td><td>{{ $orden->cliente->numero_documento }}</td></tr>
    <tr><td class="k">Teléfono</td><td>{{ $orden->cliente->telefono }}</td><td class="k">Convenio</td><td>{{ $orden->cliente->empresaColaboradora?->nombre ?? 'No aplica' }}</td></tr>
    <tr><td class="k">Placa</td><td><b>{{ $orden->vehiculo->placa_formateada }}</b></td><td class="k">Vehículo</td><td>{{ $orden->vehiculo->modelo->marca->nombre }} {{ $orden->vehiculo->modelo->nombre }} {{ $orden->vehiculo->anio }} · {{ $orden->vehiculo->color }}</td></tr>
    <tr><td class="k">Ingreso</td><td>{{ $orden->fecha_ingreso->format('d/m/Y H:i') }}</td><td class="k">Entrega</td><td>{{ $orden->fecha_entrega?->format('d/m/Y H:i') ?? '—' }}</td></tr>
    <tr><td class="k">Km ingreso</td><td>{{ number_format($orden->kilometraje_ingreso, 0, ',', '.') }}</td><td class="k">Próximo km</td><td>{{ $orden->proximo_kilometraje ? number_format($orden->proximo_kilometraje, 0, ',', '.') : '—' }}</td></tr>
    <tr><td class="k">Combustible</td><td>{{ OrdenTrabajo::NIVELES_COMBUSTIBLE[$orden->nivel_combustible] ?? '—' }}</td><td class="k">Asesor / Técnico</td><td>{{ $orden->asesor->nombre_completo }} / {{ $orden->tecnico?->nombre_completo ?? '—' }}</td></tr>
</table>

<h3>Recepción</h3>
<table class="box">
    <tr><td class="k">Observaciones</td><td colspan="3">{{ $orden->observaciones_recepcion ?: '—' }}</td></tr>
    @if ($orden->checklist)
        <tr><td class="k">Checklist</td><td colspan="3">
            @foreach ($orden->checklist as $c)
                {{ ($c['ok'] ?? false) ? '[✓]' : '[✗]' }} {{ $c['nombre'] }}@if(! empty($c['nota'])) ({{ $c['nota'] }})@endif{{ $loop->last ? '' : ' · ' }}
            @endforeach
        </td></tr>
    @endif
    <tr><td class="k">Diagnóstico</td><td colspan="3">{{ $orden->diagnostico ?: '—' }}</td></tr>
</table>

<h3>Detalle</h3>
<table class="items">
    <thead><tr><th style="width:52%">Descripción</th><th>Tipo</th><th class="r">Cant.</th><th class="r">P. unit.</th><th class="r">Subtotal</th></tr></thead>
    <tbody>
    @forelse ($orden->items as $it)
        <tr><td>{{ $it->descripcion }}</td><td>{{ OtItem::TIPOS[$it->tipo] }}</td><td class="r">{{ $it->cantidad }}</td><td class="r">{{ Dinero::r($it->precio_unitario) }}</td><td class="r">{{ Dinero::r($it->subtotal) }}</td></tr>
    @empty
        <tr><td colspan="5" class="muted">Sin ítems.</td></tr>
    @endforelse
    </tbody>
</table>

<table style="margin-top:6px">
    <tr>
        <td style="width:60%; vertical-align:top" class="muted">
            Cobro: <b>{{ $orden->estado_cobro === 'pagado' ? 'Pagado' : 'Pendiente' }}</b>
            @if ($orden->forma_cobro) ({{ OrdenTrabajo::FORMAS_COBRO[$orden->forma_cobro] }}) @endif
            <br>Valores en dólares de los Estados Unidos (USD). Este documento no es una factura.
        </td>
        <td style="width:40%">
            <table class="tot">
                <tr><td>Subtotal</td><td class="r">{{ Dinero::mostrar($orden->subtotal) }}</td></tr>
                <tr><td>Descuento @if($orden->cliente->empresaColaboradora && $orden->descuento > 0)({{ $pct($orden->cliente->empresaColaboradora->porcentaje_descuento) }}%)@endif</td><td class="r">− {{ Dinero::mostrar($orden->descuento) }}</td></tr>
                <tr><td>IVA {{ $pct($empresa?->iva_porcentaje ?? 15) }}%</td><td class="r">{{ Dinero::mostrar($orden->iva) }}</td></tr>
                <tr class="g"><td>TOTAL</td><td class="r">{{ Dinero::mostrar($orden->total) }}</td></tr>
            </table>
        </td>
    </tr>
</table>

<table class="firmas">
    <tr><td><span>Firma del cliente</span></td><td><span>Recibido por (asesor)</span></td></tr>
</table>
</body>
</html>
