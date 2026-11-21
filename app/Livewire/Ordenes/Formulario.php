<?php

namespace App\Livewire\Ordenes;

use App\Models\Auditoria;
use App\Models\Cita;
use App\Models\Cliente;
use App\Models\Empleado;
use App\Models\Modelo;
use App\Models\OrdenTrabajo;
use App\Models\Servicio;
use App\Models\Vehiculo;
use App\Rules\CedulaRucEcuador;
use App\Rules\PlacaEcuador;
use App\Services\CalculadorDescuento;
use App\Support\Catalogos;
use App\Support\Dinero;
use App\Support\Placa;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.panel')]
class Formulario extends Component
{
    public ?OrdenTrabajo $orden = null;

    // Cliente y vehículo
    public ?int $cliente_id = null;

    public ?int $vehiculo_id = null;

    public string $buscarCliente = '';

    // Alta rápida (modal)
    public bool $modalAlta = false;

    public bool $altaSoloVehiculo = false;

    public array $nuevo = [];

    // Ingreso
    public ?int $sede_id = null;

    public string $fecha_ingreso = '';

    public $kilometraje_ingreso = null;

    public $proximo_kilometraje = null;

    public bool $proximoManual = false;

    public ?string $nivel_combustible = '1/2';

    // Recepción y diagnóstico
    public string $observaciones_recepcion = '';

    public array $checklist = [];

    public string $diagnostico = '';

    public ?int $tecnico_id = null;

    /** Solo si el usuario no tiene empleado vinculado (p. ej. un superadmin): quién figura como asesor. */
    public ?int $asesor_id = null;

    // Presupuesto: ítems servicio_fijo | libre
    public array $items = [];

    // Estado y cobro
    public string $estado = 'recibido';

    public string $estado_cobro = 'pendiente';

    public ?string $forma_cobro = null;

    // Conversión desde cita
    public ?int $cita_id = null;

    public function mount(?OrdenTrabajo $orden = null): void
    {
        if ($orden?->exists) {
            $this->orden = $orden;
            $this->fill($orden->only([
                'cliente_id', 'vehiculo_id', 'sede_id', 'kilometraje_ingreso', 'proximo_kilometraje', 'nivel_combustible',
                'tecnico_id', 'estado', 'estado_cobro', 'forma_cobro',
            ]));
            $this->fecha_ingreso = $orden->fecha_ingreso->format('Y-m-d\TH:i');
            $this->observaciones_recepcion = (string) $orden->observaciones_recepcion;
            $this->diagnostico = (string) $orden->diagnostico;
            $this->checklist = $orden->checklist ?? [];
            $this->proximoManual = true;
            $this->items = $orden->items()
                ->whereIn('tipo', ['servicio_fijo', 'libre'])
                ->get()
                ->map(fn ($i) => [
                    'tipo' => $i->tipo, 'servicio_id' => $i->servicio_id, 'descripcion' => $i->descripcion,
                    'cantidad' => (string) $i->cantidad, 'precio_unitario' => (string) $i->precio_unitario,
                ])->all();

            return;
        }

        $this->fecha_ingreso = now()->format('Y-m-d\TH:i');
        $this->sede_id = auth()->user()->empleado?->sede_id ?? Catalogos::sedesActivas()->min('id');
        $this->checklist = Catalogos::checklist()
            ->map(fn ($c) => ['nombre' => $c->nombre, 'ok' => true, 'nota' => ''])->all();
        $this->resetNuevo();

        // ?cita=ID → "Convertir en OT"
        if ($cita = Cita::find(request()->integer('cita'))) {
            $this->prefillDesdeCita($cita);
        } elseif ($vehiculo = Vehiculo::find(request()->integer('vehiculo'))) {
            // ?vehiculo=ID → desde la ficha del vehículo
            $this->cliente_id = $vehiculo->cliente_id;
            $this->vehiculo_id = $vehiculo->id;
        }
    }

    private function prefillDesdeCita(Cita $cita): void
    {
        $this->cita_id = $cita->id;
        $this->sede_id = $cita->sede_id;
        $this->observaciones_recepcion = trim('Cita: '.$cita->motivo.'. '.($cita->observacion ?? ''));

        $vehiculo = $cita->vehiculo ?? Vehiculo::porPlaca($cita->placa);
        if ($vehiculo) {
            $this->cliente_id = $vehiculo->cliente_id;
            $this->vehiculo_id = $vehiculo->id;
        } else {
            // Placa no registrada: abrir alta rápida con lo que dejó la cita.
            $this->nuevo['placa'] = Placa::formatear($cita->placa);
            $this->nuevo['nombres'] = $cita->cliente_nombre;
            $this->nuevo['telefono'] = $cita->cliente_telefono;
            $this->modalAlta = true;
        }
    }

    private function resetNuevo(): void
    {
        $this->nuevo = [
            'tipo_documento' => 'cedula', 'numero_documento' => '', 'nombres' => '', 'telefono' => '', 'email' => '',
            'direccion' => '', 'empresa_colaboradora_id' => '',
            'placa' => '', 'marca' => '', 'modelo' => '', 'anio' => '', 'color' => '', 'tipo_combustible' => 'gasolina',
        ];
    }

    // ───────────── Cliente / Vehículo ─────────────

    #[Computed]
    public function resultadosCliente()
    {
        if (mb_strlen(trim($this->buscarCliente)) < 2) {
            return collect();
        }

        return Cliente::buscar($this->buscarCliente)->with('vehiculos.modelo.marca')->orderBy('nombres')->limit(8)->get();
    }

    #[Computed]
    public function cliente(): ?Cliente
    {
        return $this->cliente_id ? Cliente::with(['empresaColaboradora', 'vehiculos.modelo.marca'])->find($this->cliente_id) : null;
    }

    #[Computed]
    public function vehiculo(): ?Vehiculo
    {
        if (! $this->vehiculo_id) {
            return null;
        }

        // Reutiliza el vehículo ya cargado con el cliente (vehiculos.modelo.marca) si corresponde;
        // si el cliente cacheado en este request es otro o no lo tiene, se consulta.
        $cli = $this->cliente;
        $enCliente = $cli && $cli->id === $this->cliente_id ? $cli->vehiculos->firstWhere('id', $this->vehiculo_id) : null;

        return $enCliente ?? Vehiculo::with('modelo.marca')->find($this->vehiculo_id);
    }

    public function seleccionarCliente(int $id, ?int $vehiculoId = null): void
    {
        $cliente = Cliente::with('vehiculos')->findOrFail($id);
        $this->cliente_id = $cliente->id;

        // Si se buscó por placa, seleccionar ese vehículo; si tiene uno solo, también.
        $placa = Placa::normalizar($this->buscarCliente);
        $porPlaca = $cliente->vehiculos->first(fn ($v) => str_starts_with($v->placa, $placa) && $placa !== '' && ! ctype_digit($placa));
        $this->vehiculo_id = $vehiculoId ?? $porPlaca?->id ?? ($cliente->vehiculos->count() === 1 ? $cliente->vehiculos->first()->id : null);

        $this->buscarCliente = '';
        $this->recalcularProximo();
    }

    public function quitarCliente(): void
    {
        $this->cliente_id = null;
        $this->vehiculo_id = null;
    }

    public function updatedVehiculoId(): void
    {
        $this->recalcularProximo();
    }

    public function abrirAlta(bool $soloVehiculo = false): void
    {
        $this->resetNuevo();
        $this->resetValidation();
        $this->altaSoloVehiculo = $soloVehiculo && $this->cliente_id;
        if (! $soloVehiculo && $this->buscarCliente !== '') {
            $t = trim($this->buscarCliente);
            if (ctype_digit($t)) {
                $this->nuevo['numero_documento'] = $t;
                $this->nuevo['tipo_documento'] = strlen($t) === 13 ? 'ruc' : 'cedula';
            } elseif (Placa::pareceValida($t)) {
                $this->nuevo['placa'] = Placa::formatear($t);
            } else {
                $this->nuevo['nombres'] = $t;
            }
        }
        $this->modalAlta = true;
    }

    public function updatedNuevoMarca(): void
    {
        $this->nuevo['modelo'] = '';
    }

    #[Computed]
    public function modelosSugeridos()
    {
        return Catalogos::modelosDe($this->nuevo['marca'] ?? '');
    }

    public function guardarAlta(): void
    {
        $this->nuevo['placa'] = Placa::normalizar($this->nuevo['placa']);

        $reglas = [
            'nuevo.placa' => ['required', new PlacaEcuador, Rule::unique('vehiculos', 'placa')],
            'nuevo.marca' => ['required', 'string', 'max:60'],
            'nuevo.modelo' => ['required', 'string', 'max:80'],
            'nuevo.anio' => ['required', 'integer', 'min:1950', 'max:'.(date('Y') + 1)],
            'nuevo.color' => ['required', 'string', 'max:40'],
            'nuevo.tipo_combustible' => ['required', Rule::in(array_keys(Vehiculo::COMBUSTIBLES))],
        ];
        if (! $this->altaSoloVehiculo) {
            $reglas += [
                'nuevo.tipo_documento' => ['required', Rule::in(array_keys(Cliente::TIPOS_DOCUMENTO))],
                'nuevo.numero_documento' => array_filter([
                    'required', 'string', 'max:20', Rule::unique('clientes', 'numero_documento'),
                    match ($this->nuevo['tipo_documento']) {
                        'cedula' => 'digits:10', 'ruc' => 'digits:13', default => null,
                    },
                    $this->nuevo['tipo_documento'] !== 'pasaporte' ? new CedulaRucEcuador($this->nuevo['tipo_documento']) : null,
                ]),
                'nuevo.nombres' => ['required', 'string', 'max:150'],
                'nuevo.telefono' => ['required', 'regex:/^\+?\d{9,13}$/'],
                'nuevo.email' => ['nullable', 'email', 'max:120'],
                'nuevo.direccion' => ['nullable', 'string', 'max:255'],
                'nuevo.empresa_colaboradora_id' => ['nullable', Rule::exists('empresas_colaboradoras', 'id')->whereNull('deleted_at')],
            ];
        }
        $this->validate($reglas, [], [
            'nuevo.placa' => 'placa', 'nuevo.marca' => 'marca', 'nuevo.modelo' => 'modelo', 'nuevo.anio' => 'año',
            'nuevo.color' => 'color', 'nuevo.numero_documento' => 'cédula/RUC', 'nuevo.nombres' => 'nombre',
            'nuevo.telefono' => 'teléfono', 'nuevo.email' => 'correo',
        ]);

        DB::transaction(function () {
            $clienteId = $this->cliente_id;
            if (! $this->altaSoloVehiculo) {
                $clienteId = Cliente::create([
                    'tipo_documento' => $this->nuevo['tipo_documento'],
                    'numero_documento' => trim($this->nuevo['numero_documento']),
                    'nombres' => trim($this->nuevo['nombres']),
                    'telefono' => $this->nuevo['telefono'],
                    'email' => $this->nuevo['email'] ?: null,
                    'direccion' => $this->nuevo['direccion'] ?: null,
                    'empresa_colaboradora_id' => $this->nuevo['empresa_colaboradora_id'] ?: null,
                ])->id;
            }

            // VIN, km y fotos NO se piden en el alta rápida (se editan en la ficha del vehículo).
            $vehiculo = Vehiculo::create([
                'placa' => $this->nuevo['placa'],
                'cliente_id' => $clienteId,
                'modelo_id' => Modelo::resolver($this->nuevo['marca'], $this->nuevo['modelo'])->id,
                'anio' => (int) $this->nuevo['anio'],
                'color' => trim($this->nuevo['color']),
                'tipo_combustible' => $this->nuevo['tipo_combustible'],
            ]);

            $this->cliente_id = $clienteId;
            $this->vehiculo_id = $vehiculo->id;
        });

        $this->modalAlta = false;
        $this->buscarCliente = '';
        $this->recalcularProximo();
    }

    // ───────────── Kilometraje ─────────────

    public function updatedKilometrajeIngreso(): void
    {
        $this->recalcularProximo();
    }

    public function updatedProximoKilometraje(): void
    {
        $this->proximoManual = $this->proximo_kilometraje !== '' && $this->proximo_kilometraje !== null;
    }

    public function recalcularProximo(bool $forzar = false): void
    {
        unset($this->vehiculo); // invalida el computed cacheado
        if ($forzar) {
            $this->proximoManual = false;
        }
        if ($this->proximoManual || ! is_numeric($this->kilometraje_ingreso)) {
            return;
        }
        $intervalo = $this->vehiculo?->intervalo_mantenimiento_km ?? config('taller.mantenimiento.intervalo_km');
        $this->proximo_kilometraje = (int) $this->kilometraje_ingreso + $intervalo;
    }

    // ───────────── Ítems ─────────────

    public function agregarServicio(int $id): void
    {
        $s = Servicio::where('activo', true)->findOrFail($id);
        $this->items[] = [
            'tipo' => 'servicio_fijo', 'servicio_id' => $s->id, 'descripcion' => $s->nombre,
            'cantidad' => '1', 'precio_unitario' => (string) $s->precio_referencial,
        ];
    }

    public function agregarLibre(): void
    {
        $this->items[] = ['tipo' => 'libre', 'servicio_id' => null, 'descripcion' => '', 'cantidad' => '1', 'precio_unitario' => '0.00'];
    }

    // TODO INVENTARIO: aquí el otro dev agrega líneas tipo=producto que descuentan stock.
    // Este formulario solo gestiona 'servicio_fijo' y 'libre'; al guardar NO toca las
    // líneas tipo=producto existentes y las incluye en los totales (ver productosExistentes()).

    public function quitarItem(int $i): void
    {
        unset($this->items[$i]);
        $this->items = array_values($this->items);
    }

    /** Líneas tipo=producto ya cargadas por el módulo Inventario (solo lectura aquí). Una consulta por request. */
    #[Computed]
    public function productosExistentes()
    {
        return $this->orden?->exists
            ? $this->orden->items()->where('tipo', 'producto')->get(['descripcion', 'cantidad', 'precio_unitario', 'subtotal'])
            : collect();
    }

    #[Computed]
    public function totales(): array
    {
        $lineas = collect($this->items)
            ->map(fn ($i) => ['cantidad' => is_numeric($i['cantidad']) ? $i['cantidad'] : 0, 'precio_unitario' => is_numeric($i['precio_unitario']) ? $i['precio_unitario'] : 0])
            ->concat($this->productosExistentes->map(fn ($p) => $p->only(['cantidad', 'precio_unitario'])));

        // IVA histórico: una OT existente usa el % guardado; una nueva, el vigente de empresa.
        return app(CalculadorDescuento::class)->calcular($lineas, $this->cliente, $this->orden?->iva_porcentaje);
    }

    // ───────────── Asesor y estado ─────────────

    /** El usuario no tiene empleado vinculado y la OT es nueva: debe elegir el asesor. */
    #[Computed]
    public function requiereAsesor(): bool
    {
        return ! $this->orden?->exists && ! auth()->user()->empleado;
    }

    #[Computed]
    public function asesores()
    {
        return $this->requiereAsesor
            ? Empleado::asesoresActivos()->orderBy('apellidos')->get(['id', 'nombres', 'apellidos', 'cargo'])
            : collect();
    }

    /** Estados que se pueden elegir: el actual y las transiciones permitidas (config/taller.php). */
    #[Computed]
    public function estadosElegibles(): array
    {
        return ($this->orden ?? new OrdenTrabajo)->estadosElegibles(auth()->user());
    }

    // ───────────── Guardar ─────────────

    protected function rules(): array
    {
        return [
            'cliente_id' => ['required', 'exists:clientes,id'],
            'vehiculo_id' => ['required', Rule::exists('vehiculos', 'id')->where('cliente_id', $this->cliente_id)],
            'sede_id' => ['required', 'exists:sedes,id'],
            'fecha_ingreso' => ['required', 'date'],
            'kilometraje_ingreso' => ['required', 'integer', 'min:0', 'max:2000000'],
            'proximo_kilometraje' => ['nullable', 'integer', 'gt:kilometraje_ingreso'],
            'nivel_combustible' => ['nullable', Rule::in(array_keys(OrdenTrabajo::NIVELES_COMBUSTIBLE))],
            'observaciones_recepcion' => ['nullable', 'string', 'max:2000'],
            'diagnostico' => ['nullable', 'string', 'max:4000'],
            'checklist' => ['array'],
            'checklist.*.ok' => ['boolean'],
            'checklist.*.nota' => ['nullable', 'string', 'max:150'],
            'tecnico_id' => ['nullable', Rule::exists('empleados', 'id')->whereIn('cargo', config('taller.ordenes.cargos_tecnico'))],
            'asesor_id' => [$this->requiereAsesor ? 'required' : 'nullable',
                Rule::exists('empleados', 'id')->whereIn('cargo', config('taller.ordenes.cargos_asesor'))->where('activo', true)],
            'items' => ['array'],
            'items.*.tipo' => ['required', Rule::in(['servicio_fijo', 'libre'])],
            'items.*.descripcion' => ['required', 'string', 'max:255'],
            'items.*.cantidad' => ['required', 'numeric', 'min:0.01', 'max:9999'],
            'items.*.precio_unitario' => ['required', 'numeric', 'min:0', 'max:999999'],
            'estado' => ['required', Rule::in($this->estadosElegibles)],
            'estado_cobro' => ['required', Rule::in(['pendiente', 'pagado'])],
            'forma_cobro' => ['nullable', 'required_if:estado_cobro,pagado', Rule::in(array_keys(OrdenTrabajo::FORMAS_COBRO))],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'cliente_id' => 'cliente', 'vehiculo_id' => 'vehículo', 'asesor_id' => 'asesor', 'kilometraje_ingreso' => 'kilometraje de ingreso',
            'proximo_kilometraje' => 'próximo kilometraje', 'items.*.descripcion' => 'descripción del ítem',
            'items.*.cantidad' => 'cantidad', 'items.*.precio_unitario' => 'precio unitario', 'forma_cobro' => 'forma de cobro',
        ];
    }

    public function guardar()
    {
        $this->validate(null, [
            'estado.in' => 'Ese cambio de estado no está permitido desde el estado actual de la orden.',
        ]);

        // Asesor: el de la OT si ya existe; si no, el empleado del usuario; si no tiene, el elegido en el selector.
        $asesorId = $this->orden?->asesor_id ?? auth()->user()->empleado?->id ?? $this->asesor_id;
        $estadoAnterior = $this->orden?->getRawOriginal('estado');

        $orden = DB::transaction(function () use ($asesorId, $estadoAnterior) {
            $cliente = Cliente::with('empresaColaboradora')->findOrFail($this->cliente_id);

            $items = collect($this->items)->map(fn ($i) => [
                'tipo' => $i['tipo'],
                'servicio_id' => $i['tipo'] === 'servicio_fijo' ? $i['servicio_id'] : null,
                'descripcion' => trim($i['descripcion']),
                'cantidad' => Dinero::r($i['cantidad']),
                'precio_unitario' => Dinero::r($i['precio_unitario']),
                'subtotal' => CalculadorDescuento::subtotalLinea($i['cantidad'], $i['precio_unitario']),
            ]);

            $totales = app(CalculadorDescuento::class)->calcular(
                $items->concat($this->productosExistentes->map(fn ($p) => $p->only(['cantidad', 'precio_unitario']))),
                $cliente,
                $this->orden?->iva_porcentaje // null en una OT nueva → IVA vigente (el observer lo guarda en la OT)
            );

            $datos = [
                'sede_id' => $this->sede_id,
                'vehiculo_id' => $this->vehiculo_id,
                'cliente_id' => $cliente->id,
                'asesor_id' => $asesorId,
                'tecnico_id' => $this->tecnico_id ?: null,
                'fecha_ingreso' => $this->fecha_ingreso,
                'kilometraje_ingreso' => (int) $this->kilometraje_ingreso,
                'proximo_kilometraje' => $this->proximo_kilometraje ?: null,
                'nivel_combustible' => $this->nivel_combustible ?: null,
                'observaciones_recepcion' => $this->observaciones_recepcion ?: null,
                'checklist' => array_values($this->checklist),
                'diagnostico' => $this->diagnostico ?: null,
                'estado' => $this->estado,
                'estado_cobro' => $this->estado_cobro,
                'forma_cobro' => $this->estado_cobro === 'pagado' ? $this->forma_cobro : null,
            ] + collect($totales)->only(['subtotal', 'descuento', 'iva', 'total'])->all();

            if ($this->orden?->exists) {
                $orden = $this->orden;
                $orden->update($datos);
                if ($estadoAnterior !== $orden->estado && OrdenTrabajo::esEstadoFinal($estadoAnterior)) {
                    Auditoria::registrar('reabierto', $orden, ['estado' => $estadoAnterior], ['estado' => $orden->estado]);
                }
            } else {
                $orden = OrdenTrabajo::create($datos); // el observer genera el código OT-AAAA-NNNN
            }

            // Reemplaza solo las líneas de este formulario; las tipo=producto son del módulo Inventario.
            $orden->items()->whereIn('tipo', ['servicio_fijo', 'libre'])->delete();
            $orden->items()->createMany($items->all());

            if ($this->cita_id) {
                Cita::whereKey($this->cita_id)->update(['orden_id' => $orden->id, 'estado' => 'atendida']);
            }

            return $orden;
        });

        session()->flash('ok', "Orden {$orden->codigo} guardada correctamente.");

        return $this->redirectRoute('ordenes.edit', $orden, navigate: true);
    }

    public function render()
    {
        // Técnicos activos desde la caché; si la OT tiene asignado uno ya inactivo, se agrega.
        $tecnicos = Catalogos::tecnicos();
        if ($this->tecnico_id && ! $tecnicos->contains('id', $this->tecnico_id) && ($asignado = Empleado::find($this->tecnico_id))) {
            $tecnicos = $tecnicos->push($asignado)->sortBy('nombres', SORT_NATURAL | SORT_FLAG_CASE)->values();
        }

        return view('livewire.ordenes.formulario', [
            'servicios' => Catalogos::servicios(),
            'tecnicos' => $tecnicos,
            'sedes' => Catalogos::sedesActivas(),
            'convenios' => Catalogos::convenios(),
            'estados' => $this->estadosElegibles,
            'marcas' => Catalogos::marcas(),
            'productos' => $this->productosExistentes,
            'historial' => $this->orden?->historialEstados()->with('empleado:id,nombres,apellidos')->get() ?? collect(),
        ])->title($this->orden ? 'OT '.$this->orden->codigo : 'Nueva orden de trabajo');
    }
}
