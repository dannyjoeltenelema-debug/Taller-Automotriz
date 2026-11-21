<div>
    <div class="mb-3">
        <h1 class="page-title">Logs del sistema</h1>
        <div class="page-sub">storage/logs, solo lectura. Lo más reciente primero</div>
    </div>

    <div class="panel">
        <div class="panel-head justify-start flex-wrap">
            <select class="inp w-auto" wire:model.live="archivo" aria-label="Archivo">
                @forelse ($archivos as $a)
                    <option value="{{ $a['nombre'] }}" wire:key="log-{{ $a['nombre'] }}">{{ $a['nombre'] }} ({{ number_format($a['kb'], 1) }} KB)</option>
                @empty
                    <option value="">No hay archivos de log</option>
                @endforelse
            </select>
            <select class="inp w-auto" wire:model.live="nivel" aria-label="Nivel">
                <option value="">Todos los niveles</option>
                @foreach ($niveles as $n)<option value="{{ $n }}" wire:key="niv-{{ $n }}">{{ strtoupper($n) }}</option>@endforeach
            </select>
            <span class="muted text-[12px] ml-auto">Página {{ $pagina }}</span>
        </div>

        <div class="relative">
            <div wire:loading.delay wire:target="archivo,nivel,irA" class="absolute inset-0 bg-white/60 z-[1] p-3 space-y-2" aria-hidden="true">
                @for ($i = 0; $i < 6; $i++)<div class="skel skel-pulso" style="width: {{ 90 - $i * 7 }}%"></div>@endfor
            </div>
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead><tr><th style="width:150px">Fecha</th><th style="width:90px">Nivel</th><th>Mensaje</th></tr></thead>
                    <tbody>
                    @forelse ($resultado['entradas'] as $i => $e)
                        <tr wire:key="ent-{{ $pagina }}-{{ $i }}" x-data="{ abierto: false }">
                            <td class="whitespace-nowrap align-top">{{ $e['fecha'] }}</td>
                            <td class="align-top"><span @class(['badge', 'st-cancelado' => in_array($e['nivel'], ['emergency', 'alert', 'critical', 'error']), 'st-pendiente' => $e['nivel'] === 'warning', 'st-recibido' => ! in_array($e['nivel'], ['emergency', 'alert', 'critical', 'error', 'warning'])])>{{ strtoupper($e['nivel']) }}</span></td>
                            <td class="align-top">
                                <div class="break-all">{{ $e['mensaje'] }}</div>
                                @if (trim($e['detalle']) !== '')
                                    <button type="button" class="link text-[12px]" @click="abierto = !abierto" x-text="abierto ? 'Ocultar detalle' : 'Ver detalle'">Ver detalle</button>
                                    <pre x-show="abierto" x-cloak x-transition:enter="anim-enter" x-transition:enter-start="anim-from-up" class="mt-1 text-[11px] whitespace-pre-wrap break-all bg-[#F9FAFB] border border-[var(--c-border-soft)] p-2 max-h-72 overflow-auto">{{ $e['detalle'] }}</pre>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center muted py-6">Sin entradas{{ $nivel ? ' de nivel '.strtoupper($nivel) : '' }}.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="px-3 py-2 border-t border-[var(--c-border)] flex justify-between">
            <button class="btn btn-sm" wire:click="irA({{ $pagina - 1 }})" @disabled($pagina <= 1)>« Más recientes</button>
            <button class="btn btn-sm" wire:click="irA({{ $pagina + 1 }})" @disabled(! $resultado['hay_mas'])>Más antiguos »</button>
        </div>
    </div>
</div>
