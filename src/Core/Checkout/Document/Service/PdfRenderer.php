<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Service;

use Dompdf\Adapter\CPDF;
use Dompdf\Dompdf;
use Dompdf\Options;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Framework\Log\Package;

#[Package('customer-order')]
final class PdfRenderer
{
    public const FILE_EXTENSION = 'pdf';

    public const FILE_CONTENT_TYPE = 'application/pdf';

    /**
     * @internal
     *
     * @param array<string, mixed> $dompdfOptions
     */
    public function __construct(private array $dompdfOptions)
    {
    }

    public function getContentType(): string
    {
        return self::FILE_CONTENT_TYPE;
    }

    public function render(RenderedDocument $document): string
    {
        $dompdf = new Dompdf();

        $options = new Options($this->dompdfOptions);

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

        $this->injectPageCount($dompdf);

        if ($gcEnabledAtStart) {
            gc_enable();
        }

        return (string) $dompdf->output();
    }

    /**
     * Replace a predefined placeholder with the total page count in the whole PDF document
     */
    private function injectPageCount(Dompdf $dompdf): void
    {
        /** @var CPDF $canvas */
        $canvas = $dompdf->getCanvas();
        $search = $this->insertNullByteBeforeEachCharacter('DOMPDF_PAGE_COUNT_PLACEHOLDER');
        $replace = $this->insertNullByteBeforeEachCharacter((string) $canvas->get_page_count());
        $pdf = $canvas->get_cpdf();

        foreach ($pdf->objects as &$o) {
            if ($o['t'] === 'contents') {
                $o['c'] = str_replace($search, $replace, (string) $o['c']);
            }
        }
    }

    private function insertNullByteBeforeEachCharacter(string $string): string
    {
        return "\u{0000}" . substr(chunk_split($string, 1, "\u{0000}"), 0, -1);
    }
}
