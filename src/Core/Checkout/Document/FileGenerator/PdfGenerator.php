<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\FileGenerator;

use Dompdf\Adapter\CPDF;
use Dompdf\Dompdf;
use Dompdf\Options;
use Shopware\Core\Checkout\Document\GeneratedDocument;
use Shopware\Core\Framework\Feature;

/**
 * @deprecated tag:v6.5.0 - Will be removed, use PdfRenderer instead
 */
class PdfGenerator implements FileGeneratorInterface
{
    public const FILE_EXTENSION = 'pdf';
    public const FILE_CONTENT_TYPE = 'application/pdf';

    public function supports(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0')
        );

        return FileTypes::PDF;
    }

    public function getExtension(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0')
        );

        return self::FILE_EXTENSION;
    }

    public function getContentType(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0')
        );

        return self::FILE_CONTENT_TYPE;
    }

    public function generate(GeneratedDocument $generatedDocument): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            'Will be removed, use PdfRenderer::render instead'
        );

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->setIsHtml5ParserEnabled(true);
        $dompdf = new Dompdf($options);
        $dompdf->setPaper($generatedDocument->getPageSize(), $generatedDocument->getPageOrientation());
        $dompdf->loadHtml($generatedDocument->getHtml());

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

        return $dompdf->output() ?? '';
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
                $o['c'] = str_replace($search, $replace, $o['c']);
            }
        }
    }

    private function insertNullByteBeforeEachCharacter(string $string): string
    {
        return "\u{0000}" . substr(chunk_split($string, 1, "\u{0000}"), 0, -1);
    }
}
