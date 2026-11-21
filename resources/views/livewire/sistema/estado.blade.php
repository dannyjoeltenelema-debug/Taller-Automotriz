<div>
    <div class="flex flex-wrap items-end justify-between gap-2 mb-3">
        <div>
            <h1 class="page-title">Estado del sistema</h1>
            <div class="page-sub">Versiones, entorno y tamaño de la base de datos. Esta pantalla no ejecuta migraciones ni nada destructivo</div>
        </div>
        <button class="btn" wire:click="limpiarCaches" wire:loading.attr="disabled" wire:target="limpiarCaches">
            <span wire:loading.remove wire:target="limpiarCaches">Limpiar cachés de la app</span><span wire:loading wire:target="limpiarCaches">Limpiando…</span>
        </button>
    </div>

    <div class="grid lg:grid-cols-[380px_1fr] gap-3 items-start">
        <div class="panel">
            <div class="panel-head">Aplicación</div>
            <table class="tbl">
                <tbody>
                @foreach ($info as $k => $v)
                    <tr wire:key="info-{{ $loop->index }}"><td class="muted">{{ $k }}</td><td class="font-medium">{{ $v }}</td></tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <div class="panel">
            <div class="panel-head">Tablas</div>
            @if ($tablas === null)
                <div class="panel-body muted">El motor de base de datos actual no informa tamaños de tabla.</div>
            @else
                <div class="tbl-wrap max-h-[480px] overflow-y-auto">
                    <table class="tbl">
                        <thead><tr><th>Tabla</th><th class="num">Filas (aprox.)</th><th class="num">Tamaño (MB)</th></tr></thead>
                        <tbody>
                        @foreach ($tablas as $t)
                            <tr wire:key="tbl-{{ $t->tabla }}"><td>{{ $t->tabla }}</td><td class="num">{{ number_format((int) $t->filas) }}</td><td class="num">{{ number_format((float) $t->mb, 2) }}</td></tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
