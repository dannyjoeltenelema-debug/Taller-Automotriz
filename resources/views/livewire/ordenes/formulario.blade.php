@php
    use App\Models\OrdenTrabajo;
    use App\Models\Vehiculo;
    use App\Support\Dinero;
    $t = $this->totales;
    $cli = $this->cliente;
    $veh = $this->vehiculo;
@endphp
<div>
    <div class="flex flex-wrap items-end justify-between gap-2 mb-3">
        <div>
            <div class="page-sub"><a href="{{ route('ordenes.index') }}" wire:navigate class="link">Órdenes de trabajo</a> /</div>
            <h1 class="page-title">
                @if ($orden)
                    Orden {{ $orden->codigo }} <x-estado :estado="$orden->estado" class="align-middle ml-1" />
                @else
                    Nueva orden de trabajo
                @endif
            </h1>
            @if ($cita_id)<div class="page-sub">Convirtiendo la cita #{{ $cita_id }} en orden de trabajo.</div>@endif
        </div>
        <div class="flex gap-2">
            @if ($orden)
                <a href="{{ route('ordenes.pdf', $orden) }}" target="_blank" class="btn">Imprimir OT (PDF)</a>
            @endif
            <button type="button" wire:click="guardar" wire:loading.attr="disabled" class="btn btn-primary">
                <span wire:loading.remove wire:target="guardar">Guardar orden</span>
                <span wire:loading wire:target="guardar">Guardando…</span>
            </button>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-err mb-3">Revise los campos marcados ({{ $errors->count() }}).</div>
    @endif

    <div class="grid lg:grid-cols-3 gap-3">
        <div class="lg:col-span-2 space-y-3">

            {{-- ── Cliente y vehículo ── --}}
            <section class="panel">
                <div class="panel-head">1. Cliente y vehículo</div>
                <div class="panel-body space-y-3">
                    @if (! $cli)
                        <div class="relative" x-data @click.outside="$wire.set('buscarCliente', '')">
                            <label class="lbl">Buscar cliente registrado <span class="req">*</span></label>
                            <div class="flex gap-2">
                                <input type="search" class="inp" wire:model.live.debounce.300ms="buscarCliente" placeholder="Cédula/RUC, nombre o placa" autofocus>
                                <button type="button" class="btn" wire:click="abrirAlta">+ Nuevo Cliente / Auto</button>
                            </div>
                            @if (mb_strlen(trim($buscarCliente)) >= 2)
                                <div class="dropdown">
                                    @forelse ($this->resultadosCliente as $r)
                                        <button type="button" wire:click="seleccionarCliente({{ $r->id }})" wire:key="rc-{{ $r->id }}">
                                            <strong>{{ $r->nombres }}</strong> <span class="muted">· {{ $r->numero_documento }}</span>
                                            <div class="muted text-[12px]">
                                                @foreach ($r->vehiculos as $v) <span class="placa">{{ $v->placa_formateada }}</span> {{ $v->modelo->marca->nombre }} {{ $v->modelo->nombre }}@if(! $loop->last), @endif @endforeach
                                                @if ($r->vehiculos->isEmpty()) Sin vehículos @endif
                                            </div>
                                        </button>
                                    @empty
                                        <div class="px-3 py-2 muted">Sin resultados. Use «+ Nuevo Cliente / Auto».</div>
                                    @endforelse
                                </div>
                            @endif
                            @error('cliente_id') <div class="err">{{ $message }}</div> @enderror
                        </div>
                    @else
                        <div class="flex flex-wrap justify-between gap-2 border border-[var(--c-border)] rounded-[3px] p-2 bg-[#FAFAFB]">
                            <div>
                                <div class="font-semibold">{{ $cli->nombres }}</div>
                                <div class="muted text-[12.5px]">
                                    {{ \App\Models\Cliente::TIPOS_DOCUMENTO[$cli->tipo_documento] }} {{ $cli->numero_documento }} · Tel. {{ $cli->telefono }}
                                    @if ($cli->email) · {{ $cli->email }} @endif
                                </div>
                                <div class="text-[12.5px] mt-1">Convenio:
                                    @if ($cli->empresaColaboradora)
                                        <strong>{{ $cli->empresaColaboradora->nombre }}</strong> ({{ rtrim(rtrim($cli->empresaColaboradora->porcentaje_descuento, '0'), '.') }}% desc.)
                                    @else <span class="muted">No aplica</span> @endif
                                </div>
                            </div>
                            @unless ($orden)
                                <button type="button" class="btn btn-sm" wire:click="quitarCliente">Cambiar cliente</button>
                            @endunless
                        </div>
                        <div class="grid sm:grid-cols-[1fr_auto] gap-2 items-end">
                            <x-campo label="Vehículo" :req="true" error="vehiculo_id">
                                <select class="inp" wire:model.live="vehiculo_id">
                                    <option value="">— Seleccione —</option>
                                    @foreach ($cli->vehiculos as $v)
                                        <option value="{{ $v->id }}" wire:key="vo-{{ $v->id }}">{{ $v->placa_formateada }} · {{ $v->modelo->marca->nombre }} {{ $v->modelo->nombre }} {{ $v->anio }} · {{ $v->color }}</option>
                                    @endforeach
                                </select>
                            </x-campo>
                            <button type="button" class="btn" wire:click="abrirAlta(true)">+ Otro vehículo</button>
                        </div>
                    @endif
                </div>
            </section>

            {{-- ── Datos de ingreso ── --}}
            <section class="panel">
                <div class="panel-head">2. Datos de ingreso</div>
                <div class="panel-body grid sm:grid-cols-3 gap-3">
                    <x-campo label="Sede" :req="true" error="sede_id">
                        <select class="inp" wire:model="sede_id">
                            @foreach ($sedes as $s) <option value="{{ $s->id }}" wire:key="sede-{{ $s->id }}">{{ $s->nombre }}</option> @endforeach
                        </select>
                    </x-campo>
                    <x-campo label="Fecha y hora de ingreso" :req="true" error="fecha_ingreso">
                        <input type="datetime-local" class="inp" wire:model="fecha_ingreso">
                    </x-campo>
                    <x-campo label="Nivel de combustible" error="nivel_combustible">
                        <select class="inp" wire:model="nivel_combustible">
                            @foreach (OrdenTrabajo::NIVELES_COMBUSTIBLE as $k => $v) <option value="{{ $k }}">{{ $v }}</option> @endforeach
                        </select>
                    </x-campo>
                    <x-campo label="Kilometraje de ingreso" :req="true" error="kilometraje_ingreso">
                        <input type="number" min="0" class="inp" wire:model.live.debounce.500ms="kilometraje_ingreso" placeholder="Ej. 45380">
                        @if ($veh?->kilometraje_actual)<div class="muted text-[11.5px]">Último registrado: {{ number_format($veh->kilometraje_actual, 0, ',', '.') }} km</div>@endif
                    </x-campo>
                    <x-campo label="Próximo km recomendado" error="proximo_kilometraje">
                        <div class="flex gap-1">
                            <input type="number" min="0" class="inp" wire:model.blur="proximo_kilometraje">
                            <button type="button" class="btn btn-sm h-[31px]" title="Recalcular (km + intervalo)" wire:click="recalcularProximo(true)">↺</button>
                        </div>
                        <div class="muted text-[11.5px]">km + {{ number_format($veh?->intervalo_mantenimiento_km ?? config('taller.mantenimiento.intervalo_km'), 0, ',', '.') }} (editable)</div>
                    </x-campo>
                    <div class="flex items-end">
                        @if ($veh)
                            <a href="{{ route('vehiculos.etiqueta', $veh) }}" target="_blank" class="btn w-full justify-center">Vista previa / Imprimir Etiqueta QR</a>
                        @else
                            <button class="btn w-full justify-center" disabled>Vista previa / Imprimir Etiqueta QR</button>
                        @endif
                    </div>
                </div>
            </section>

            {{-- ── Recepción ── --}}
            <section class="panel">
                <div class="panel-head">3. Recepción</div>
                <div class="panel-body grid md:grid-cols-2 gap-3">
                    <x-campo label="Observaciones generales" error="observaciones_recepcion">
                        <textarea class="inp" rows="7" wire:model="observaciones_recepcion" placeholder="Estado general, pedido del cliente, objetos de valor…"></textarea>
                    </x-campo>
                    <div>
                        <label class="lbl">Checklist de recepción</label>
                        <table class="tbl border border-[var(--c-border)]">
                            <thead><tr><th class="w-10">OK</th><th>Ítem</th><th>Nota</th></tr></thead>
                            <tbody>
                            @forelse ($checklist as $i => $c)
                                <tr wire:key="chk-{{ $i }}">
                                    <td class="text-center"><input type="checkbox" wire:model="checklist.{{ $i }}.ok"></td>
                                    <td>{{ $c['nombre'] }}</td>
                                    <td><input type="text" class="inp h-[25px]" wire:model="checklist.{{ $i }}.nota" maxlength="150"></td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="muted">No hay ítems de checklist activos (ver Catálogos).</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            {{-- ── Diagnóstico y presupuesto ── --}}
            <section class="panel">
                <div class="panel-head">4. Diagnóstico y presupuesto</div>
                <div class="panel-body space-y-3">
                    <x-campo label="Diagnóstico" error="diagnostico">
                        <textarea class="inp" rows="3" wire:model="diagnostico"></textarea>
                    </x-campo>

                    <div>
                        <label class="lbl">Servicios fijos (clic para agregar)</label>
                        <div class="flex flex-wrap gap-1">
                            @foreach ($servicios as $s)
                                <button type="button" class="btn btn-sm" wire:click="agregarServicio({{ $s->id }})" wire:key="srv-{{ $s->id }}">
                                    {{ $s->nombre }} <span class="muted">{{ Dinero::mostrar($s->precio_referencial) }}</span>
                                </button>
                            @endforeach
                            <button type="button" class="btn btn-sm" style="border-style:dashed" wire:click="agregarLibre">+ Agregar ítem libre</button>
                        </div>
                    </div>

                    <div class="tbl-wrap border border-[var(--c-border)]">
                        <table class="tbl">
                            <thead><tr><th>Descripción</th><th>Tipo</th><th class="num w-20">Cant.</th><th class="num w-28">P. unit.</th><th class="num w-24">Subtotal</th><th class="w-8"></th></tr></thead>
                            <tbody>
                            @forelse ($items as $i => $it)
                                <tr wire:key="item-{{ $i }}-{{ $it['tipo'] }}-{{ $it['servicio_id'] }}">
                                    <td>
                                        <input type="text" class="inp h-[26px]" wire:model.blur="items.{{ $i }}.descripcion" placeholder="Descripción del trabajo o repuesto">
                                        @error("items.$i.descripcion") <div class="err">{{ $message }}</div> @enderror
                                    </td>
                                    <td class="whitespace-nowrap">{{ $it['tipo'] === 'servicio_fijo' ? 'Servicio fijo' : 'Libre' }}</td>
                                    <td><input type="number" step="0.01" min="0" class="inp h-[26px] text-right" wire:model.blur="items.{{ $i }}.cantidad">
                                        @error("items.$i.cantidad") <div class="err">{{ $message }}</div> @enderror</td>
                                    <td><input type="number" step="0.01" min="0" class="inp h-[26px] text-right" wire:model.blur="items.{{ $i }}.precio_unitario">
                                        @error("items.$i.precio_unitario") <div class="err">{{ $message }}</div> @enderror</td>
                                    <td class="num">{{ Dinero::r(\App\Services\CalculadorDescuento::subtotalLinea(is_numeric($it['cantidad']) ? $it['cantidad'] : 0, is_numeric($it['precio_unitario']) ? $it['precio_unitario'] : 0)) }}</td>
                                    <td><button type="button" class="btn btn-sm btn-danger" wire:click="quitarItem({{ $i }})" title="Quitar">✕</button></td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="muted text-center py-3">Agregue servicios fijos o ítems libres.</td></tr>
                            @endforelse
                            {{-- Líneas tipo=producto: las gestiona el módulo Inventario (solo lectura aquí). --}}
                            @foreach ($productos as $p)
                                <tr class="muted" wire:key="prod-{{ $loop->index }}">
                                    <td>{{ $p->descripcion }}</td><td>Producto</td>
                                    <td class="num">{{ $p->cantidad }}</td><td class="num">{{ $p->precio_unitario }}</td><td class="num">{{ $p->subtotal }}</td><td></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>

        {{-- ── Columna derecha ── --}}
        <div class="space-y-3">
            <section class="panel">
                <div class="panel-head">Técnico y estado</div>
                <div class="panel-body space-y-3">
                    <x-campo label="Técnico asignado" error="tecnico_id">
                        <select class="inp" wire:model="tecnico_id">
                            <option value="">— Sin asignar —</option>
                            @foreach ($tecnicos as $tec)
                                <option value="{{ $tec->id }}" wire:key="tec-{{ $tec->id }}">{{ $tec->nombre_completo }}{{ $tec->especialidad ? ' · '.$tec->especialidad : '' }}</option>
                            @endforeach
                        </select>
                    </x-campo>
                    <x-campo label="Asesor">
                        <input class="inp" readonly value="{{ $orden?->asesor?->nombre_completo ?? auth()->user()->empleado?->nombre_completo ?? auth()->user()->name }}">
                    </x-campo>
                    <x-campo label="Estado de la orden" error="estado">
                        <select class="inp" wire:model="estado">
                            @foreach (OrdenTrabajo::ESTADOS as $k => $v) <option value="{{ $k }}">{{ $v }}</option> @endforeach
                        </select>
                    </x-campo>
                </div>
            </section>

            <section class="panel">
                <div class="panel-head">Resumen</div>
                <div class="panel-body">
                    <table class="w-full text-[13.5px]">
                        <tr><td class="py-1">Subtotal</td><td class="num text-right">{{ Dinero::mostrar($t['subtotal']) }}</td></tr>
                        <tr><td class="py-1">Descuento @if($t['porcentaje_descuento'] > 0)<span class="muted">(convenio {{ rtrim(rtrim(number_format($t['porcentaje_descuento'], 2), '0'), '.') }}%)</span>@endif</td>
                            <td class="text-right">− {{ Dinero::mostrar($t['descuento']) }}</td></tr>
                        <tr><td class="py-1">IVA {{ rtrim(rtrim(number_format($t['porcentaje_iva'], 2), '0'), '.') }}%</td><td class="text-right">{{ Dinero::mostrar($t['iva']) }}</td></tr>
                        <tr class="border-t border-[var(--c-border)] font-semibold text-[15px]"><td class="pt-2">Total</td><td class="pt-2 text-right">{{ Dinero::mostrar($t['total']) }}</td></tr>
                    </table>
                </div>
            </section>

            <section class="panel">
                <div class="panel-head">Cobro</div>
                <div class="panel-body grid grid-cols-2 gap-2">
                    <x-campo label="Estado de cobro" error="estado_cobro">
                        <select class="inp" wire:model.live="estado_cobro">
                            <option value="pendiente">Pendiente</option>
                            <option value="pagado">Pagado</option>
                        </select>
                    </x-campo>
                    <x-campo label="Forma de cobro" error="forma_cobro">
                        <select class="inp" wire:model="forma_cobro" @disabled($estado_cobro !== 'pagado')>
                            <option value="">—</option>
                            @foreach (OrdenTrabajo::FORMAS_COBRO as $k => $v) <option value="{{ $k }}">{{ $v }}</option> @endforeach
                        </select>
                    </x-campo>
                </div>
            </section>

            @if ($historial->isNotEmpty())
                <section class="panel">
                    <div class="panel-head">Historial de estados</div>
                    <table class="tbl">
                        @foreach ($historial as $h)
                            <tr wire:key="hist-{{ $h->id }}">
                                <td class="whitespace-nowrap muted">{{ $h->created_at->format('d/m H:i') }}</td>
                                <td><x-estado :estado="$h->estado_nuevo" /></td>
                                <td class="muted text-[12px]">{{ $h->empleado?->nombre_completo ?? 'Sistema' }}</td>
                            </tr>
                        @endforeach
                    </table>
                </section>
            @endif
        </div>
    </div>

    {{-- ── Modal: Registro rápido de cliente y vehículo ── --}}
    @if ($modalAlta)
        <div class="modal-back" wire:keydown.escape="$set('modalAlta', false)">
            <form class="modal" wire:submit="guardarAlta">
                <div class="modal-head">
                    <span>{{ $altaSoloVehiculo ? 'Agregar vehículo a '.$cli?->nombres : 'Registro Rápido de Cliente y Vehículo' }}</span>
                    <button type="button" wire:click="$set('modalAlta', false)" aria-label="Cerrar">✕</button>
                </div>
                <div class="p-4 space-y-4">
                    @unless ($altaSoloVehiculo)
                        <fieldset>
                            <legend class="font-semibold mb-2">Cliente</legend>
                            <div class="grid sm:grid-cols-2 gap-3">
                                <x-campo label="Nombre / Razón social" :req="true" error="nuevo.nombres" class="sm:col-span-2">
                                    <input class="inp" wire:model="nuevo.nombres">
                                </x-campo>
                                <x-campo label="Documento" :req="true" error="nuevo.numero_documento">
                                    <div class="flex gap-1">
                                        <select class="inp w-28" wire:model.live="nuevo.tipo_documento">
                                            @foreach (\App\Models\Cliente::TIPOS_DOCUMENTO as $k => $v) <option value="{{ $k }}">{{ $v }}</option> @endforeach
                                        </select>
                                        <input class="inp" wire:model="nuevo.numero_documento" inputmode="numeric"
                                               placeholder="{{ $nuevo['tipo_documento'] === 'ruc' ? '13 dígitos' : ($nuevo['tipo_documento'] === 'cedula' ? '10 dígitos' : '') }}">
                                    </div>
                                </x-campo>
                                <x-campo label="Teléfono WhatsApp" :req="true" error="nuevo.telefono">
                                    <input class="inp" wire:model="nuevo.telefono" inputmode="tel" placeholder="09XXXXXXXX">
                                </x-campo>
                                <x-campo label="Correo (opcional)" error="nuevo.email">
                                    <input type="email" class="inp" wire:model="nuevo.email">
                                </x-campo>
                                <x-campo label="Dirección (opcional)" error="nuevo.direccion">
                                    <input class="inp" wire:model="nuevo.direccion">
                                </x-campo>
                                <x-campo label="Empresa colaboradora / Descuento" error="nuevo.empresa_colaboradora_id" class="sm:col-span-2">
                                    <select class="inp" wire:model="nuevo.empresa_colaboradora_id">
                                        <option value="">No aplica</option>
                                        @foreach ($convenios as $cv)
                                            <option value="{{ $cv->id }}" wire:key="conv-{{ $cv->id }}">{{ $cv->nombre }} ({{ rtrim(rtrim($cv->porcentaje_descuento, '0'), '.') }}%)</option>
                                        @endforeach
                                    </select>
                                </x-campo>
                            </div>
                        </fieldset>
                    @endunless
                    <fieldset>
                        <legend class="font-semibold mb-2">Vehículo</legend>
                        <div class="grid sm:grid-cols-3 gap-3">
                            <x-campo label="Placa" :req="true" error="nuevo.placa">
                                <input class="inp placa uppercase" wire:model="nuevo.placa" placeholder="PBA-4521">
                            </x-campo>
                            <x-campo label="Marca" :req="true" error="nuevo.marca">
                                <input class="inp" list="dl-marcas" wire:model.live.debounce.400ms="nuevo.marca" autocomplete="off">
                                <datalist id="dl-marcas">@foreach ($marcas as $m)<option value="{{ $m }}">@endforeach</datalist>
                            </x-campo>
                            <x-campo label="Modelo" :req="true" error="nuevo.modelo">
                                <input class="inp" list="dl-modelos" wire:model="nuevo.modelo" autocomplete="off">
                                <datalist id="dl-modelos">@foreach ($this->modelosSugeridos as $m)<option value="{{ $m }}">@endforeach</datalist>
                            </x-campo>
                            <x-campo label="Año" :req="true" error="nuevo.anio">
                                <input type="number" class="inp" wire:model="nuevo.anio" min="1950" max="{{ date('Y') + 1 }}">
                            </x-campo>
                            <x-campo label="Color" :req="true" error="nuevo.color">
                                <input class="inp" wire:model="nuevo.color">
                            </x-campo>
                            <x-campo label="Tipo de combustible" error="nuevo.tipo_combustible">
                                <select class="inp" wire:model="nuevo.tipo_combustible">
                                    @foreach (Vehiculo::COMBUSTIBLES as $k => $v) <option value="{{ $k }}">{{ $v }}</option> @endforeach
                                </select>
                            </x-campo>
                        </div>
                        <p class="muted text-[12px] mt-2">VIN/chasis, motor y transmisión se completan luego en la ficha del vehículo.</p>
                    </fieldset>
                </div>
                <div class="modal-foot">
                    <button type="button" class="btn" wire:click="$set('modalAlta', false)">Cancelar</button>
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Registrar y seleccionar</button>
                </div>
            </form>
        </div>
    @endif
</div>
