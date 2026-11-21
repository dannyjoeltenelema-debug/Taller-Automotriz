<?php

namespace App\Livewire\Sistema;

use App\Support\LectorLog;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Visor de storage/logs: solo lectura, paginado desde el final, filtro por nivel. */
#[Layout('components.layouts.panel')]
#[Title('Logs del sistema')]
class Logs extends Component
{
    use SoloSuperadmin;

    #[Url(except: '')]
    public string $archivo = '';

    #[Url(except: '')]
    public string $nivel = '';

    #[Url(except: 1)]
    public int $pagina = 1;

    public function mount(): void
    {
        if (! LectorLog::ruta($this->archivo)) {
            $this->archivo = LectorLog::archivos()[0]['nombre'] ?? '';
        }
    }

    public function updatedArchivo(): void
    {
        $this->pagina = 1;
    }

    public function updatedNivel(): void
    {
        $this->pagina = 1;
    }

    public function irA(int $pagina): void
    {
        $this->pagina = max(1, $pagina);
    }

    public function render()
    {
        $nivel = in_array($this->nivel, LectorLog::NIVELES, true) ? $this->nivel : null;
        $ruta = LectorLog::ruta($this->archivo);

        return view('livewire.sistema.logs', [
            'archivos' => LectorLog::archivos(),
            'niveles' => LectorLog::NIVELES,
            'resultado' => $ruta
                ? LectorLog::pagina($ruta, max(1, $this->pagina), config('taller.paginacion.logs'), $nivel)
                : ['entradas' => [], 'hay_mas' => false],
        ]);
    }
}
