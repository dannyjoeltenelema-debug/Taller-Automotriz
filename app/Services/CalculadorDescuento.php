<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\Empresa;
use App\Support\Dinero;

/**
 * Totales de una OT.
 *  subtotal  = Σ round(cantidad × precio_unitario, 2)
 *  descuento = round(subtotal × % convenio del cliente, 2)   (0 si "No aplica")
 *  iva       = round((subtotal − descuento) × IVA empresa, 2)
 *  total     = (subtotal − descuento) + iva
 * Cada monto se redondea a 2 decimales ANTES de sumar.
 */
class CalculadorDescuento
{
    /**
     * @param  iterable<array{cantidad: mixed, precio_unitario: mixed}>  $items
     * @return array{subtotal: string, descuento: string, iva: string, total: string, porcentaje_descuento: float, porcentaje_iva: float}
     */
    public function calcular(iterable $items, ?Cliente $cliente = null, ?float $ivaPorcentaje = null): array
    {
        $porcDesc = $cliente?->porcentajeDescuento() ?? 0.0;
        $porcIva = $ivaPorcentaje ?? Empresa::ivaPorcentaje();

        $subtotal = 0.0;
        foreach ($items as $item) {
            $subtotal += (float) self::subtotalLinea($item['cantidad'] ?? 0, $item['precio_unitario'] ?? 0);
        }
        $subtotal = (float) Dinero::r($subtotal);

        $descuento = (float) Dinero::r($subtotal * $porcDesc / 100);
        $base = (float) Dinero::r($subtotal - $descuento);
        $iva = (float) Dinero::r($base * $porcIva / 100);
        $total = (float) Dinero::r($base + $iva);

        return [
            'subtotal' => Dinero::r($subtotal),
            'descuento' => Dinero::r($descuento),
            'iva' => Dinero::r($iva),
            'total' => Dinero::r($total),
            'porcentaje_descuento' => $porcDesc,
            'porcentaje_iva' => $porcIva,
        ];
    }

    public static function subtotalLinea(mixed $cantidad, mixed $precio): string
    {
        return Dinero::r((float) Dinero::r($cantidad) * (float) Dinero::r($precio));
    }
}
