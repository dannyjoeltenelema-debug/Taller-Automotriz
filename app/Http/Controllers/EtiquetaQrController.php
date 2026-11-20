<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\Vehiculo;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/** Etiqueta imprimible con QR (SVG inline) → vista pública /q/{qr_token}. */
class EtiquetaQrController extends Controller
{
    public function __invoke(Vehiculo $vehiculo)
    {
        $vehiculo->load('modelo.marca', 'cliente');
        $url = route('publico.vehiculo', $vehiculo->qr_token);

        return view('etiqueta-qr', [
            'vehiculo' => $vehiculo,
            'empresa' => Empresa::actual(),
            'url' => $url,
            'qr' => QrCode::format('svg')->size(180)->margin(0)->errorCorrection('M')->generate($url),
            'ultimaOrden' => $vehiculo->ultimaOrden(),
        ]);
    }
}
