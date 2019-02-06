<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Generator;

use Dompdf\Dompdf;
use Dompdf\Options;

class PdfGenerator implements DocumentGeneratorInterface
{
    public function generateAsString(string $html): string
    {
        return $this->generate($html)->output();
    }

    public function generateAsStream(string $html): void
    {
        $this->generate($html)->stream();
    }

    protected function generate(string $html): Dompdf
    {
        $options = new Options();
        $options->setIsHtml5ParserEnabled(true);
        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->loadHtml($html);

        /*
         * Dompdf creates and destroys a lot of objects. The garbage collector slows the process down by ~50% for
         * PHP <7.3
         */
        $gcEnabledAtStart = gc_enabled();
        if ($gcEnabledAtStart) {
            gc_collect_cycles();
            gc_disable();
        }

        $dompdf->render();

        if ($gcEnabledAtStart) {
            gc_enable();
        }

        return $dompdf;
    }
}
