<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Reglas del movimiento (Frente B) verificadas sobre el CSS fuente:
 * solo opacity/transform, duraciones ≤ 250 ms (salvo el resaltado de fila nueva, ~1 s),
 * nada de rebotes, glow, sombras animadas ni animaciones infinitas.
 */
class MovimientoCssTest extends TestCase
{
    private function css(): string
    {
        // Sin comentarios: solo cuentan las declaraciones.
        return preg_replace('#/\*.*?\*/#s', '', file_get_contents(__DIR__.'/../../resources/css/movimiento.css'));
    }

    public function test_solo_se_animan_opacity_y_transform(): void
    {
        preg_match_all('/transition:\s*([^;]+);/', $this->css(), $m);
        foreach ($m[1] as $decl) {
            foreach (explode(',', $decl) as $parte) {
                $prop = strtok(trim($parte), ' ');
                $this->assertContains($prop, ['opacity', 'transform'], "transition sobre '{$prop}'");
            }
        }

        preg_match_all('/@keyframes\s+[\w-]+\s*\{(.+?\})\s*\}/s', $this->css(), $k);
        foreach ($k[1] as $cuerpo) {
            preg_match_all('/([\w-]+)\s*:/', $cuerpo, $props);
            foreach ($props[1] as $prop) {
                $this->assertContains($prop, ['opacity', 'transform'], "keyframe anima '{$prop}'");
            }
        }
    }

    public function test_duraciones_cortas_y_sin_efectos_prohibidos(): void
    {
        $css = $this->css();
        preg_match_all('/--mov-\w+:\s*(\d+)ms/', $css, $m);
        $this->assertNotEmpty($m[1]);
        foreach ($m[1] as $ms) {
            $this->assertGreaterThanOrEqual(120, (int) $ms);
            $this->assertLessThanOrEqual(250, (int) $ms);
        }

        // Duraciones escritas a mano: solo el resaltado de fila nueva (1s).
        preg_match_all('/animation:[^;]*?\b(\d+(?:\.\d+)?)(ms|s)\b/', $css, $d, PREG_SET_ORDER);
        foreach ($d as [$decl, $n, $u]) {
            $ms = $u === 's' ? (float) $n * 1000 : (float) $n;
            $this->assertTrue($ms <= 250 || str_contains($decl, 'mov-apaga'), "duración larga: {$decl}");
        }

        foreach (['infinite', 'cubic-bezier(.68', 'box-shadow', 'filter:', 'gradient', 'bounce', 'spring'] as $prohibido) {
            $this->assertStringNotContainsString($prohibido, $css);
        }
        $this->assertStringContainsString('prefers-reduced-motion', $css);
    }
}
