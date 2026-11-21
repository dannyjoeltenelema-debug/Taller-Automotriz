<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\Empresa;
use App\Support\Dinero;

/**
 * Totales de una OT, con aritmética exacta (App\Support\Dinero: BCMath o centavos; nunca float).
 *  subtotal  = Σ round(cantidad × precio_unitario, 2)
 *  descuento = round(subtotal × % convenio del cliente, 2)   (0 si "No aplica")
 *  iva       = round((subtotal − descuento) × IVA, 2)        (IVA guardado en la OT o el vigente de empresa)
 *  total     = (subtotal − descuento) + iva
 * Cada monto se redondea a 2 decimales ANTES de sumar.
 */
class CalculadorDescuento
{
    /**
     * @param  iterable<array{cantidad: mixed, precio_unitario: mixed}>  $items
     * @return array{subtotal: string, descuento: string, iva: string, total: string, porcentaje_descuento: float, porcentaje_iva: float}
     */
    public function calcular(iterable $items, ?Cliente $cliente = null, float|string|null $ivaPorcentaje = null): array
    {
        $porcDesc = $cliente?->porcentajeDescuento() ?? 0.0;
        $porcIva = $ivaPorcentaje ?? Empresa::ivaPorcentaje();

        $lineas = [];
        foreach ($items as $item) {
            $lineas[] = self::subtotalLinea($item['cantidad'] ?? 0, $item['precio_unitario'] ?? 0);
        }
        $subtotal = Dinero::sumar(...$lineas);

        $descuento = Dinero::porcentaje($subtotal, $porcDesc);
        $base = Dinero::restar($subtotal, $descuento);
        $iva = Dinero::porcentaje($base, $porcIva);
        $total = Dinero::sumar($base, $iva);

        return [
            'subtotal' => $subtotal,
            'descuento' => $descuento,
            'iva' => $iva,
            'total' => $total,
            'porcentaje_descuento' => (float) $porcDesc,
            'porcentaje_iva' => (float) $porcIva,
        ];
    }

    public static function subtotalLinea(mixed $cantidad, mixed $precio): string
    {
        return Dinero::multiplicar(is_numeric($cantidad) ? $cantidad : 0, is_numeric($precio) ? $precio : 0);
    }
}
