<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Service;

use Dompdf\Adapter\CPDF;
use Dompdf\Dompdf;
use Dompdf\Options;
use Shopware\Core\Checkout\Document\DocumentConfiguration;
use Shopware\Core\Checkout\Document\DocumentConfigurationFactory;
use Shopware\Core\Checkout\Document\DocumentException;
use Shopware\Core\Checkout\Document\Extension\PdfRendererExtension;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Checkout\Document\Twig\DocumentTemplateRenderer;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

#[Package('after-sales')]
class PdfRenderer extends AbstractDocumentTypeRenderer
{
    public const FILE_EXTENSION = 'pdf';

    public const FILE_CONTENT_TYPE = 'application/pdf';

    /**
     * @internal
     *
     * @param array<string, mixed> $dompdfOptions
     */
    public function __construct(
        private readonly array $dompdfOptions,
        private readonly DocumentTemplateRenderer $documentTemplateRenderer,
        private readonly string $rootDir,
        private readonly ExtensionDispatcher $extensions
    ) {
    }

    public function getContentType(): string
    {
        return self::FILE_CONTENT_TYPE;
    }

    public function render(RenderedDocument $document): string
    {
        return $this->extensions->publish(
            name: PdfRendererExtension::NAME,
            extension: new PdfRendererExtension($document),
            function: $this->_render(...)
        );
    }

    public function getDecorated(): AbstractDocumentTypeRenderer
    {
        throw new DecorationPatternException(self::class);
    }

    private function _render(RenderedDocument $document): string
    {
        $dompdf = new Dompdf();

        $options = new Options($this->dompdfOptions);

        $dompdf->setOptions($options);
        $dompdf->setPaper($document->getPageSize(), $document->getPageOrientation());
        $dompdf->loadHtml($this->getHtml($document));

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

    private function getHtml(RenderedDocument $document): string
    {
        if ($document->getHtml() !== '') {
            return $document->getHtml();
        }

        $document->setContentType(self::FILE_CONTENT_TYPE);
        $document->setFileExtension(self::FILE_EXTENSION);

        if (!$document->getOrder() || !$document->getContext()) {
            throw DocumentException::documentGenerationException('No options provided for rendering the document.');
        }

        $config = DocumentConfigurationFactory::mergeConfiguration(
            new DocumentConfiguration(),
            $document->getConfig(),
        );

        $language = $document->getOrder()->getLanguage();

        return $this->documentTemplateRenderer->render(
            $document->getTemplate(),
            [
                'order' => $document->getOrder(),
                'config' => $config,
                'rootDir' => $this->rootDir,
                'context' => $document->getContext(),
            ],
            $document->getContext(),
            $document->getOrder()->getSalesChannelId(),
            $document->getOrder()->getLanguageId(),
            $language?->getLocale()?->getCode(),
        );
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
