<?php

namespace App\Support;

/**
 * Lectura paginada de storage/logs/*.log SIN cargar el archivo entero en memoria:
 * se lee desde el final hacia atrás en bloques y se arma cada entrada
 * ("[2026-11-20 10:00:00] local.ERROR: …" + sus líneas de traza).
 *
 * Solo lectura: no borra ni modifica archivos. La ruta siempre se resuelve dentro de storage/logs.
 */
class LectorLog
{
    public const NIVELES = ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'];

    private const BLOQUE = 65536;

    private const MAX_DETALLE = 4000; // caracteres de traza que se conservan por entrada

    /** @return list<array{nombre: string, kb: float, modificado: int}> archivos .log, el más reciente primero */
    public static function archivos(): array
    {
        $lista = [];
        foreach (glob(storage_path('logs/*.log')) ?: [] as $ruta) {
            $lista[] = ['nombre' => basename($ruta), 'kb' => round(filesize($ruta) / 1024, 1), 'modificado' => filemtime($ruta)];
        }
        usort($lista, fn ($a, $b) => $b['modificado'] <=> $a['modificado']);

        return $lista;
    }

    /** Ruta segura: solo un nombre de archivo .log existente dentro de storage/logs. */
    public static function ruta(?string $nombre): ?string
    {
        $nombre = basename((string) $nombre);
        if (! str_ends_with($nombre, '.log')) {
            return null;
        }
        $ruta = storage_path('logs/'.$nombre);

        return is_file($ruta) ? $ruta : null;
    }

    /**
     * Entradas de la página pedida (la más reciente primero).
     *
     * @return array{entradas: list<array{fecha: string, nivel: string, mensaje: string, detalle: string}>, hay_mas: bool}
     */
    public static function pagina(string $ruta, int $pagina = 1, int $porPagina = 100, ?string $nivel = null): array
    {
        $necesarias = $pagina * $porPagina + 1; // +1 para saber si hay otra página
        $encontradas = [];

        $f = fopen($ruta, 'rb');
        if (! $f) {
            return ['entradas' => [], 'hay_mas' => false];
        }

        $pos = filesize($ruta);
        $resto = '';          // línea incompleta al inicio del bloque leído
        $lineasEntrada = [];  // líneas (en orden inverso) de la entrada que se está armando

        while ($pos > 0 && count($encontradas) < $necesarias) {
            $leer = min(self::BLOQUE, $pos);
            $pos -= $leer;
            fseek($f, $pos);
            $trozo = fread($f, $leer).$resto;
            $lineas = explode("\n", $trozo);
            $resto = $pos > 0 ? array_shift($lineas) : '';

            for ($i = count($lineas) - 1; $i >= 0; $i--) {
                $linea = rtrim($lineas[$i], "\r");
                $lineasEntrada[] = $linea;
                if (self::esCabecera($linea)) {
                    $entrada = self::armar(array_reverse($lineasEntrada));
                    $lineasEntrada = [];
                    if ($entrada && (! $nivel || $entrada['nivel'] === $nivel)) {
                        $encontradas[] = $entrada;
                        if (count($encontradas) >= $necesarias) {
                            break;
                        }
                    }
                } elseif (count($lineasEntrada) > 400) {
                    array_shift($lineasEntrada); // trazas enormes: se recorta, la memoria queda acotada
                }
            }
        }
        fclose($f);

        $inicio = ($pagina - 1) * $porPagina;

        return [
            'entradas' => array_slice($encontradas, $inicio, $porPagina),
            'hay_mas' => count($encontradas) > $inicio + $porPagina,
        ];
    }

    private static function esCabecera(string $linea): bool
    {
        return (bool) preg_match('/^\[\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}/', $linea);
    }

    private static function armar(array $lineas): ?array
    {
        $cabecera = array_shift($lineas);
        if (! preg_match('/^\[([^\]]+)\]\s+\w+\.(\w+):\s?(.*)$/', (string) $cabecera, $m)) {
            return null;
        }

        return [
            'fecha' => $m[1],
            'nivel' => strtolower($m[2]),
            'mensaje' => mb_substr($m[3], 0, 500),
            'detalle' => mb_substr(implode("\n", $lineas), 0, self::MAX_DETALLE),
        ];
    }
}
