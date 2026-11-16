<?php

namespace App\Services;

use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class QrCodeService
{
    /** QR sebagai string SVG (tajam di layar dan saat dicetak browser). */
    public function svg(string $content, int $size = 256, int $margin = 2): string
    {
        $renderer = new ImageRenderer(new RendererStyle($size, $margin), new SvgImageBackEnd());

        return (new Writer($renderer))->writeString($content);
    }

    public function svgDataUri(string $content, int $size = 256, int $margin = 2): string
    {
        return 'data:image/svg+xml;base64,' . base64_encode($this->svg($content, $size, $margin));
    }

    /** QR sebagai PNG (data URI), paling andal untuk DomPDF. Membutuhkan ekstensi GD. */
    public function pngDataUri(string $content, int $size = 300, int $margin = 2): string
    {
        $png = (new Writer(new GDLibRenderer($size, $margin)))->writeString($content);

        return 'data:image/png;base64,' . base64_encode($png);
    }
}
