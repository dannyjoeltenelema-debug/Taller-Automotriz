<?php

namespace App\Livewire\Sistema;

use App\Models\Auditoria as Registro;
use App\Models\Empresa;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('components.layouts.panel')]
#[Title('Auditoría')]
class Auditoria extends Component
{
    use SoloSuperadmin, WithPagination;

    /** Tope de filas del PDF (dompdf arma todo en memoria). */
    public const MAX_PDF = 1000;

    #[Url(except: '')]
    public string $usuario = '';

    #[Url(except: '')]
    public string $modulo = '';

    #[Url(except: '')]
    public string $accion = '';

    #[Url(except: '')]
    public string $desde = '';

    #[Url(except: '')]
    public string $hasta = '';

    #[Url(as: 'q', except: '')]
    public string $buscar = '';

    /** Registro cuyo diff está abierto (se renderiza solo ese: la página no carga 50 tablas de cambios). */
    public ?int $detalleId = null;

    public function verDetalle(int $id): void
    {
        $this->detalleId = $this->detalleId === $id ? null : $id;
    }

    public function updated(string $prop): void
    {
        if (in_array($prop, ['usuario', 'modulo', 'accion', 'desde', 'hasta', 'buscar'], true)) {
            $this->resetPage();
        }
    }

    public function limpiarFiltros(): void
    {
        $this->reset(['usuario', 'modulo', 'accion', 'desde', 'hasta', 'buscar']);
        $this->resetPage();
    }

    private function filtros(): array
    {
        $this->validate([
            'usuario' => ['nullable', 'integer'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
            'accion' => ['nullable', 'in:'.implode(',', array_keys(Registro::ACCIONES))],
        ]);

        return $this->only(['usuario', 'modulo', 'accion', 'desde', 'hasta', 'buscar']);
    }

    #[Computed]
    public function usuarios()
    {
        return User::orderBy('name')->get(['id', 'name', 'cedula']);
    }

    /** Exporta a PDF lo filtrado (descarga desde la acción Livewire: sin recargar la página). */
    public function exportarPdf(): StreamedResponse
    {
        $filtros = $this->filtros();
        $registros = Registro::query()->filtrar($filtros)->with('user:id,name,cedula')
            ->latest('id')->limit(self::MAX_PDF)->get();

        Registro::registrar('exportado', null, null, ['tipo' => 'auditoria_pdf', 'filas' => $registros->count()] + array_filter($filtros));

        $pdf = Pdf::loadView('pdf.auditoria', [
            'registros' => $registros,
            'filtros' => $filtros,
            'usuarios' => $this->usuarios->keyBy('id'),
            'empresa' => Empresa::actual(),
            'tope' => self::MAX_PDF,
        ])->setPaper('a4', 'landscape');

        return response()->streamDownload(fn () => print ($pdf->output()), 'auditoria-'.now()->format('Ymd-His').'.pdf');
    }

    public function render()
    {
        return view('livewire.sistema.auditoria', [
            'registros' => Registro::query()->filtrar($this->filtros())
                ->with('user:id,name,cedula')
                ->latest('id')
                ->paginate(config('taller.paginacion.auditoria')),
            'modulos' => Registro::MODULOS,
            'acciones' => Registro::ACCIONES,
        ]);
    }
}
