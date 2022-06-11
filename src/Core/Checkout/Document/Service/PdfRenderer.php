<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Service;

use Dompdf\Dompdf;
use Dompdf\Options;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;

final class PdfRenderer
{
    public const FILE_EXTENSION = 'pdf';

    public const FILE_CONTENT_TYPE = 'application/pdf';

    public function getContentType(): string
    {
        return self::FILE_CONTENT_TYPE;
    }

    public function render(RenderedDocument $document): string
    {
        $dompdf = new Dompdf();

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->setIsHtml5ParserEnabled(true);

        $dompdf->setOptions($options);
        $dompdf->setPaper($document->getPageSize(), $document->getPageOrientation());
        $dompdf->loadHtml($document->getHtml());

        /*
         * Dompdf creates and destroys a lot of objects. The garbage collector slows the process down by ~50% for
         * PHP <7.3 and still some ms for 7.4
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

        return (string) $dompdf->output();
    }
}
