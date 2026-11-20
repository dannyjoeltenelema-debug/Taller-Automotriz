<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Etiqueta QR · {{ $vehiculo->placa_formateada }}</title>
    <style>
        :root { --c-border: #D1D5DB; --c-text: #1F2937; --c-muted: #6B7280; --c-accent: #1E40AF; }
        body { font-family: system-ui, 'Segoe UI', Arial, sans-serif; color: var(--c-text); background: #F4F5F7; margin: 0; padding: 24px; }
        .barra { max-width: 420px; margin: 0 auto 12px; display: flex; gap: 8px; justify-content: flex-end; }
        .btn { border: 1px solid var(--c-border); background: #fff; padding: 5px 12px; font-size: 13px; border-radius: 3px; cursor: pointer; }
        .btn-primary { background: var(--c-accent); border-color: var(--c-accent); color: #fff; }
        .etiqueta { width: 90mm; margin: 0 auto; background: #fff; border: 1px dashed #9CA3AF; padding: 5mm; box-sizing: border-box; }
        .taller { font-weight: 700; font-size: 12px; text-align: center; border-bottom: 1px solid var(--c-border); padding-bottom: 2mm; }
        .cuerpo { display: flex; gap: 4mm; align-items: center; margin-top: 3mm; }
        .qr svg { width: 34mm; height: 34mm; display: block; }
        .placa { font-family: Consolas, monospace; font-size: 20px; font-weight: 700; letter-spacing: .05em; }
        .dato { font-size: 11px; margin-top: 1mm; }
        .muted { color: var(--c-muted); }
        .pie { font-size: 9.5px; text-align: center; margin-top: 3mm; border-top: 1px solid var(--c-border); padding-top: 2mm; }
        @media print { body { background: #fff; padding: 0; } .barra { display: none; } .etiqueta { border: 1px solid #000; margin: 0; } }
    </style>
</head>
<body>
    <div class="barra">
        <button class="btn" onclick="window.close()">Cerrar</button>
        <button class="btn btn-primary" onclick="window.print()">Imprimir etiqueta</button>
    </div>

    <div class="etiqueta">
        <div class="taller">{{ $empresa?->razon_social }}</div>
        <div class="cuerpo">
            <div class="qr">{!! $qr !!}</div>
            <div>
                <div class="placa">{{ $vehiculo->placa_formateada }}</div>
                <div class="dato">{{ $vehiculo->modelo->marca->nombre }} {{ $vehiculo->modelo->nombre }} {{ $vehiculo->anio }}</div>
                @if ($ultimaOrden)
                    <div class="dato"><span class="muted">Último servicio:</span><br>{{ $ultimaOrden->fecha_ingreso->format('d/m/Y') }} · {{ number_format($ultimaOrden->kilometraje_ingreso, 0, ',', '.') }} km</div>
                    @if ($ultimaOrden->proximo_kilometraje)
                        <div class="dato"><strong>Próximo: {{ number_format($ultimaOrden->proximo_kilometraje, 0, ',', '.') }} km</strong></div>
                    @endif
                @endif
            </div>
        </div>
        <div class="pie">Escanee para ver su historial y agendar cita<br>Tel. {{ $empresa?->telefono }}</div>
    </div>
</body>
</html>
