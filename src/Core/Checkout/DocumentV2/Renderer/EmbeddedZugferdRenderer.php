<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Renderer;

use Shopware\Core\Checkout\DocumentV2\AbstractDocumentRenderer;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\RenderInput;
use Shopware\Core\Checkout\DocumentV2\RenderResult;
use Shopware\Core\Checkout\DocumentV2\RenderState;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
class EmbeddedZugferdRenderer extends AbstractDocumentRenderer
{
    public const FORMAT = DocumentFormat::EmbeddedZugferd->value;

    public function supports(string $docType): bool
    {
        return $docType === DocumentType::Invoice->value
            || $docType === DocumentType::CancellationInvoice->value
            || $docType === DocumentType::CreditNote->value;
    }

    public function getFormat(): string
    {
        return self::FORMAT;
    }

    public function getDependencies(): array
    {
        return [PdfRenderer::FORMAT, ZugferdXmlRenderer::FORMAT];
    }

    public function renderToString(RenderInput $renderInput, RenderState $renderState): RenderResult
    {
        // TODO: Implement renderToString() method.
        $pdfResult = $renderState->getRenderedContent(PdfRenderer::FORMAT);
        if (!$pdfResult instanceof RenderResult) {
            // todo: error handling
            throw new \RuntimeException('Missing pdf renderer result');
        }
        $zugferdXmlResult = $renderState->getRenderedContent(ZugferdXmlRenderer::FORMAT);
        if (!$zugferdXmlResult instanceof RenderResult) {
            // todo: error handling
            throw new \RuntimeException('Missing zugferdXml renderer result');
        }

        return new RenderResult($pdfResult->documentContent . $zugferdXmlResult->documentContent);
    }

    public function persistToFile(RenderInput $renderInput, RenderResult $renderResult): string
    {
        // TODO: Implement persistToFile() method.
        return 'uuid-of-media-entity';
    }
}
