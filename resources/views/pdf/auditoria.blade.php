@php use App\Models\Auditoria; @endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Auditoría</title>
    <style>
        @page { margin: 12mm 10mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8.5px; color: #1F2937; }
        table { width: 100%; border-collapse: collapse; }
        .taller { font-size: 13px; font-weight: bold; }
        .muted { color: #6B7280; }
        h2 { font-size: 11px; color: #1E40AF; margin: 6px 0; }
        .items th { background: #F3F4F6; border: 1px solid #D1D5DB; padding: 3px 5px; text-align: left; }
        .items td { border: 1px solid #D1D5DB; padding: 3px 5px; vertical-align: top; }
        .diff td { border: 0; padding: 0 4px 0 0; }
    </style>
</head>
<body>
    <div class="taller">{{ $empresa?->razon_social ?? config('app.name') }}</div>
    <h2>Auditoría del sistema</h2>
    <div class="muted">
        Generado el {{ now()->format('d/m/Y H:i') }} por {{ auth()->user()->name }}.
        Filtros:
        @php
            $f = array_filter($filtros);
            $txt = [];
            if (! empty($f['usuario'])) $txt[] = 'usuario: '.($usuarios[$f['usuario']]->name ?? $f['usuario']);
            if (! empty($f['modulo'])) $txt[] = 'módulo: '.($f['modulo'] === 'acceso' ? 'Acceso' : (Auditoria::MODULOS[$f['modulo']] ?? $f['modulo']));
            if (! empty($f['accion'])) $txt[] = 'acción: '.(Auditoria::ACCIONES[$f['accion']] ?? $f['accion']);
            if (! empty($f['desde'])) $txt[] = 'desde '.$f['desde'];
            if (! empty($f['hasta'])) $txt[] = 'hasta '.$f['hasta'];
            if (! empty($f['buscar'])) $txt[] = 'búsqueda: "'.$f['buscar'].'"';
        @endphp
        {{ $txt ? implode(' · ', $txt) : 'ninguno' }}.
        {{ $registros->count() }} registros{{ $registros->count() >= $tope ? ' (máximo '.$tope.'; acote los filtros para ver el resto)' : '' }}.
    </div>
    <br>
    <table class="items">
        <thead><tr><th style="width:13%">Fecha</th><th style="width:16%">Usuario</th><th style="width:11%">Acción</th><th style="width:14%">Módulo</th><th style="width:6%">Reg.</th><th style="width:9%">IP</th><th>Cambios</th></tr></thead>
        <tbody>
        @foreach ($registros as $r)
            <tr>
                <td>{{ $r->created_at->format('d/m/Y H:i:s') }}</td>
                <td>{{ $r->user?->name ?? '—' }}</td>
                <td>{{ $r->accion_label }}</td>
                <td>{{ $r->modulo }}</td>
                <td>{{ $r->auditable_id ?? '—' }}</td>
                <td>{{ $r->ip ?? '—' }}</td>
                <td>
                    @if ($d = $r->diff())
                        <table class="diff">
                            @foreach ($d as $campo => [$antes, $despues])
                                <tr><td><b>{{ str_replace('_', ' ', ucfirst($campo)) }}:</b></td><td class="muted">{{ Str::limit(Auditoria::textoValor($antes), 80) }}</td><td>→ {{ Str::limit(Auditoria::textoValor($despues), 80) }}</td></tr>
                            @endforeach
                        </table>
                    @else — @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</body>
</html>
