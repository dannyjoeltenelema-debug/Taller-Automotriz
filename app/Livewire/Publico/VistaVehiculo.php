<?php

namespace App\Livewire\Publico;

use App\Models\Cita;
use App\Models\Empresa;
use App\Models\Sede;
use App\Models\Servicio;
use App\Models\Vehiculo;
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
        $this->hora_inicio = Cita::FRANJAS[0];
        $this->sede_id = Sede::where('activa', true)->value('id');
    }

    private function vehiculo(): Vehiculo
    {
        return once(fn () => Vehiculo::with(['modelo.marca', 'cliente'])
            ->where('qr_token', $this->token)->where('activo', true)->firstOrFail());
    }

    public function agendar(): void
    {
        $clave = 'cita-publica:'.request()->ip();
        if (RateLimiter::tooManyAttempts($clave, 3)) {
            $this->addError('motivo', 'Ya registró varias solicitudes. Intente más tarde o escríbanos por WhatsApp.');

            return;
        }

        $this->validate([
            'fecha_deseada' => ['required', 'date', 'after_or_equal:today', 'before_or_equal:'.now()->addDays(60)->toDateString()],
            'hora_inicio' => ['required', Rule::in(Cita::FRANJAS)],
            'sede_id' => ['required', Rule::exists('sedes', 'id')->where('activa', true)],
            'motivo' => ['required', 'string', 'max:150'],
            'observacion' => ['nullable', 'string', 'max:500'],
            'cliente_nombre' => ['required', 'string', 'max:150'],
            'cliente_telefono' => ['required', 'regex:/^\+?\d{9,13}$/'],
        ], [
            'fecha_deseada.before_or_equal' => 'Solo se pueden agendar citas hasta 60 días adelante.',
            'cliente_telefono.regex' => 'Ingrese un número de teléfono válido (ej. 0991234567).',
        ], [
            'fecha_deseada' => 'fecha', 'hora_inicio' => 'franja horaria', 'motivo' => 'servicio o motivo',
            'cliente_nombre' => 'nombre', 'cliente_telefono' => 'teléfono', 'sede_id' => 'sede',
        ]);

        $v = $this->vehiculo();

        // Evita duplicados: misma placa, fecha y franja con cita abierta.
        $duplicada = Cita::where('placa', $v->placa)->whereDate('fecha_deseada', $this->fecha_deseada)
            ->where('hora_inicio', $this->hora_inicio)->whereIn('estado', ['pendiente', 'confirmada'])->exists();
        if ($duplicada) {
            $this->addError('hora_inicio', 'Ya existe una solicitud para este vehículo en esa fecha y franja.');

            return;
        }

        RateLimiter::hit($clave, 600);

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

        return view('livewire.publico.vista-vehiculo', [
            'v' => $v,
            'descripcion' => $descripcion,
            'historial' => $v->ordenes()->where('estado', 'entregado')->with('items')->orderByDesc('fecha_ingreso')->limit(20)->get(),
            'enCurso' => $v->ordenes()->activas()->latest('fecha_ingreso')->first(),
            'proximoKm' => $v->proximoKilometraje(),
            'whatsapp' => $empresa?->telefono ? 'https://wa.me/'.$empresa->telefonoWhatsapp().'?text='.rawurlencode($mensaje) : null,
            'servicios' => Servicio::where('activo', true)->orderBy('nombre')->pluck('nombre'),
            'sedes' => Sede::where('activa', true)->orderBy('nombre')->get(),
        ])->title('Vehículo '.$v->placa_formateada);
    }
}
