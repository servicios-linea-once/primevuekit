<?php

declare(strict_types=1);

namespace PrimeVueKit\Auth;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Renderiza el QR de enrolamiento como SVG inline.
 *
 * El backend se inyecta explícitamente en lugar de dejar que la librería lo elija
 * según el entorno: así la salida es la misma en cualquier máquina y no depende de
 * que imagick esté instalado. Sólo necesita las extensiones `xmlwriter` e `iconv`.
 */
final class QrCodeSvg
{
    public function __construct(
        private readonly int $size = 256,
        private readonly int $margin = 1,
    ) {}

    public function render(string $content): string
    {
        $writer = new Writer(new ImageRenderer(
            new RendererStyle($this->size, $this->margin),
            new SvgImageBackEnd,
        ));

        // Se quita el prólogo XML para poder incrustar el SVG dentro del HTML.
        $svg = preg_replace('/^<\?xml.*?\?>\s*/', '', $writer->writeString($content));

        return trim($svg ?? '');
    }
}
