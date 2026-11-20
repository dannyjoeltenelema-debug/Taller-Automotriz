<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\OrdenTrabajo;
use Barryvdh\DomPDF\Facade\Pdf;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class OrdenPdfController extends Controller
{
    public function __invoke(OrdenTrabajo $orden)
    {
        $orden->load(['vehiculo.modelo.marca', 'cliente.empresaColaboradora', 'asesor', 'tecnico', 'sede', 'items']);

        $qr = base64_encode(QrCode::format('svg')->size(110)->margin(0)
            ->generate(route('publico.vehiculo', $orden->vehiculo->qr_token)));

        return Pdf::loadView('pdf.orden', [
            'orden' => $orden,
            'empresa' => Empresa::actual(),
            'qr' => $qr,
        ])->setPaper('a4')->stream($orden->codigo.'.pdf');
    }
}
