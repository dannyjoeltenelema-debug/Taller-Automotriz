<div class="max-w-md mx-auto mt-4 sm:mt-10">
    <div class="panel">
        <div class="panel-head">Consulta de vehículo</div>
        <form class="panel-body space-y-3" wire:submit="consultar">
            <p class="muted">Consulte el historial de mantenimientos, el próximo kilometraje recomendado y agende una cita.</p>

            <div class="flex border border-[var(--c-border)] rounded-[3px] overflow-hidden w-fit">
                <label @class(['px-4 py-1.5 cursor-pointer', 'bg-[var(--c-accent)] text-white' => $tipo === 'placa'])>
                    <input type="radio" class="sr-only" wire:model.live="tipo" value="placa"> Placa
                </label>
                <label @class(['px-4 py-1.5 cursor-pointer border-l border-[var(--c-border)]', 'bg-[var(--c-accent)] text-white' => $tipo === 'cedula'])>
                    <input type="radio" class="sr-only" wire:model.live="tipo" value="cedula"> Cédula / RUC
                </label>
            </div>

            <div>
                <label class="lbl" for="valor">{{ $tipo === 'placa' ? 'Número de placa' : 'Número de cédula o RUC' }}</label>
                <input id="valor" class="inp h-[38px] text-[15px] {{ $tipo === 'placa' ? 'placa uppercase' : '' }}" wire:model="valor"
                       placeholder="{{ $tipo === 'placa' ? 'Ej. PBA-4521' : 'Ej. 1712345678' }}"
                       inputmode="{{ $tipo === 'placa' ? 'text' : 'numeric' }}" autocomplete="off" autofocus>
                @error('valor') <div class="err">{{ $message }}</div> @enderror
            </div>

            <button class="btn btn-primary w-full justify-center h-[38px]" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="consultar">Consultar</span>
                <span wire:loading wire:target="consultar">Consultando…</span>
            </button>

            <p class="muted text-[12.5px]">También puede escanear el código QR de su vehículo.</p>
        </form>

        @if ($opciones)
            <div class="border-t border-[var(--c-border)]">
                <div class="px-3 pt-3 pb-1 font-semibold">Seleccione su vehículo</div>
                @foreach ($opciones as $o)
                    <a href="{{ route('publico.vehiculo', $o['token']) }}" wire:navigate class="nav-link py-2 border-t border-[var(--c-border-soft)]">
                        <span><span class="placa">{{ $o['placa'] }}</span> <span class="muted">· {{ $o['detalle'] }}</span></span> ›
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</div>
