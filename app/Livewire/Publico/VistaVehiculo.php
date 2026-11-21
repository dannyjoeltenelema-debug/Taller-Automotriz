<?php

namespace App\Livewire\Publico;

use App\Models\Cita;
use App\Models\Empresa;
use App\Models\OrdenTrabajo;
use App\Models\Vehiculo;
use App\Support\Catalogos;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Vista del cliente (la misma por QR, placa o cédula). Datos sensibles enmascarados. */
#[Layout('components.layouts.publico')]
class VistaVehiculo extends Component
{
    #[Locked]
    public string $token;

    // Formulario de cita
    public string $fecha_deseada = '';

    public string $hora_inicio = '';

    public string $motivo = '';

    public string $observacion = '';

    public string $cliente_nombre = '';

    public string $cliente_telefono = '';

    public $sede_id = null;

    public bool $citaEnviada = false;

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->vehiculo(); // 404 si no existe
        $this->fecha_deseada = now()->addDay()->toDateString();
        $this->hora_inicio = Cita::franjas()[0];
        $this->sede_id = Catalogos::sedesActivas()->min('id');
    }

    /** Vehículo + modelo + marca + propietario en una sola consulta (memo por request). */
    private function vehiculo(): Vehiculo
    {
        return once(fn () => Vehiculo::paraPortal($this->token));
    }

    public function agendar(): void
    {
        $clave = 'cita-publica:'.request()->ip();
        if (RateLimiter::tooManyAttempts($clave, config('taller.portal.citas_max'))) {
            $this->addError('motivo', 'Ya registró varias solicitudes. Intente más tarde o escríbanos por WhatsApp.');

            return;
        }

        $diasMax = config('taller.citas.dias_anticipacion_max');

        $this->validate([
            'fecha_deseada' => ['required', 'date', 'after_or_equal:today', 'before_or_equal:'.now()->addDays($diasMax)->toDateString()],
            'hora_inicio' => ['required', Rule::in(Cita::franjas())],
            'sede_id' => ['required', Rule::exists('sedes', 'id')->where('activa', true)],
            'motivo' => ['required', 'string', 'max:150'],
            'observacion' => ['nullable', 'string', 'max:500'],
            'cliente_nombre' => ['required', 'string', 'max:150'],
            'cliente_telefono' => ['required', 'regex:/^\+?\d{9,13}$/'],
        ], [
            'fecha_deseada.before_or_equal' => "Solo se pueden agendar citas hasta {$diasMax} días adelante.",
            'cliente_telefono.regex' => 'Ingrese un número de teléfono válido (ej. 0991234567).',
        ], [
            'fecha_deseada' => 'fecha', 'hora_inicio' => 'franja horaria', 'motivo' => 'servicio o motivo',
            'cliente_nombre' => 'nombre', 'cliente_telefono' => 'teléfono', 'sede_id' => 'sede',
        ]);

        $v = $this->vehiculo();

        // Evita duplicados: misma placa, fecha y franja con cita abierta.
        $duplicada = Cita::where('placa', $v->placa)->delDia($this->fecha_deseada)
            ->where('hora_inicio', $this->hora_inicio)->whereIn('estado', ['pendiente', 'confirmada'])->exists();
        if ($duplicada) {
            $this->addError('hora_inicio', 'Ya existe una solicitud para este vehículo en esa fecha y franja.');

            return;
        }

        RateLimiter::hit($clave, config('taller.portal.citas_ventana_seg'));

        Cita::create([
            'sede_id' => $this->sede_id,
            'vehiculo_id' => $v->id,
            'placa' => $v->placa,
            'cliente_nombre' => trim($this->cliente_nombre),
            'cliente_telefono' => $this->cliente_telefono,
            'fecha_deseada' => $this->fecha_deseada,
            'hora_inicio' => $this->hora_inicio,
            'motivo' => $this->motivo,
            'observacion' => $this->observacion ?: null,
            'origen' => 'qr_vehiculo',
            'estado' => 'pendiente',
        ]);

        $this->citaEnviada = true;
    }

    public function render()
    {
        $v = $this->vehiculo();
        $empresa = Empresa::actual();
        $descripcion = $v->modelo->marca->nombre.' '.$v->modelo->nombre.' '.$v->anio;

        $mensaje = "Hola, quisiera información sobre mi vehículo placa {$v->placa_formateada} ({$descripcion}).";

        // Una consulta de OTs no canceladas: de ahí salen el historial, la OT en curso y la última OT.
        $ordenes = $v->ordenes()
            ->where('estado', '!=', 'cancelado')
            ->orderByDesc('fecha_ingreso')
            ->get(['id', 'vehiculo_id', 'codigo', 'fecha_ingreso', 'estado', 'kilometraje_ingreso', 'proximo_kilometraje']);

        $historial = $ordenes->where('estado', 'entregado')->take(20)->values()->load('items:id,orden_id,descripcion');

        return view('livewire.publico.vista-vehiculo', [
            'v' => $v,
            'descripcion' => $descripcion,
            'historial' => $historial,
            'enCurso' => $ordenes->firstWhere(fn ($o) => in_array($o->estado, OrdenTrabajo::ESTADOS_ACTIVOS, true)),
            'proximoKm' => $v->proximoKilometrajeDesde($ordenes->first()),
            'whatsapp' => $empresa?->telefono ? 'https://wa.me/'.$empresa->telefonoWhatsapp().'?text='.rawurlencode($mensaje) : null,
            'servicios' => Catalogos::servicios()->pluck('nombre'),
            'sedes' => Catalogos::sedesActivas(),
            'diasMax' => config('taller.citas.dias_anticipacion_max'),
        ])->title('Vehículo '.$v->placa_formateada);
    }
}
