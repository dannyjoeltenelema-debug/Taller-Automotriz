<div>
    <div class="mb-3">
        <div class="page-sub"><a href="{{ route('vehiculos.index') }}" wire:navigate class="link">Vehículos</a> /</div>
        <h1 class="page-title">{{ $vehiculo ? 'Editar vehículo '.$vehiculo->placa_formateada : 'Nuevo vehículo' }}</h1>
    </div>

    <form wire:submit="guardar" class="panel max-w-[820px]">
        <div class="panel-head">Propietario</div>
        <div class="panel-body">
            @if ($this->cliente)
                <div class="flex justify-between items-center">
                    <div><strong>{{ $this->cliente->nombres }}</strong> <span class="muted">· {{ $this->cliente->numero_documento }}</span></div>
                    <button type="button" class="btn btn-sm" wire:click="$set('cliente_id', null)">Cambiar</button>
                </div>
            @else
                <div class="relative">
                    <input type="search" class="inp" wire:model.live.debounce.300ms="buscarCliente" placeholder="Buscar cliente por cédula/RUC o nombre">
                    @if ($this->resultadosCliente->isNotEmpty())
                        <div class="dropdown">
                            @foreach ($this->resultadosCliente as $r)
                                <button type="button" wire:click="seleccionarCliente({{ $r->id }})" wire:key="rc-{{ $r->id }}">{{ $r->nombres }} <span class="muted">· {{ $r->numero_documento }}</span></button>
                            @endforeach
                        </div>
                    @endif
                </div>
                <div class="muted text-[12px] mt-1">¿Cliente nuevo? Regístrelo en <a href="{{ route('clientes.index') }}" wire:navigate class="link">Clientes</a> o use el alta rápida de la OT.</div>
            @endif
            @error('cliente_id') <div class="err">{{ $message }}</div> @enderror
        </div>

        <div class="panel-head border-t border-[var(--c-border)]">Datos del vehículo</div>
        <div class="panel-body grid sm:grid-cols-3 gap-3">
            <x-campo label="Placa" :req="true" error="placa"><input class="inp placa uppercase" wire:model="placa" placeholder="PBA-4521"></x-campo>
            <x-campo label="Marca" :req="true" error="marca">
                <input class="inp" list="dl-marcas" wire:model.live.debounce.400ms="marca" autocomplete="off">
                <datalist id="dl-marcas">@foreach ($marcas as $m)<option value="{{ $m }}" wire:key="opt-{{ $m }}">@endforeach</datalist>
            </x-campo>
            <x-campo label="Modelo" :req="true" error="modelo">
                <input class="inp" list="dl-modelos" wire:model="modelo" autocomplete="off">
                <datalist id="dl-modelos">@foreach ($this->modelosSugeridos as $m)<option value="{{ $m }}" wire:key="opt-{{ $m }}">@endforeach</datalist>
            </x-campo>
            <x-campo label="Año" :req="true" error="anio"><input type="number" class="inp" wire:model="anio"></x-campo>
            <x-campo label="Color" :req="true" error="color"><input class="inp" wire:model="color"></x-campo>
            <x-campo label="Combustible" error="tipo_combustible">
                <select class="inp" wire:model="tipo_combustible">@foreach (\App\Models\Vehiculo::COMBUSTIBLES as $k => $v)<option value="{{ $k }}" wire:key="opt-{{ $k }}">{{ $v }}</option>@endforeach</select>
            </x-campo>
            <x-campo label="VIN / Chasis" error="vin"><input class="inp uppercase" wire:model="vin" maxlength="17"></x-campo>
            <x-campo label="N.º de motor" error="numero_motor"><input class="inp uppercase" wire:model="numero_motor"></x-campo>
            <x-campo label="Transmisión" error="transmision">
                <select class="inp" wire:model="transmision">@foreach (\App\Models\Vehiculo::TRANSMISIONES as $k => $v)<option value="{{ $k }}" wire:key="opt-{{ $k }}">{{ $v }}</option>@endforeach</select>
            </x-campo>
            <x-campo label="Intervalo de mantenimiento (km)" error="intervalo_mantenimiento_km"><input type="number" class="inp" wire:model="intervalo_mantenimiento_km"></x-campo>
            <label class="flex items-center gap-2 mt-5"><input type="checkbox" wire:model="activo"> Vehículo activo</label>
        </div>
        <div class="modal-foot">
            <span class="muted text-[12px] mr-auto" wire:dirty>Cambios sin guardar</span>
            <a href="{{ $vehiculo ? route('vehiculos.show', $vehiculo) : route('vehiculos.index') }}" wire:navigate class="btn">Cancelar</a>
            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="guardar"><span wire:loading.remove wire:target="guardar">Guardar</span><span wire:loading wire:target="guardar">Guardando…</span></button>
        </div>
    </form>
</div>
