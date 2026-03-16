<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Renderer;

use Shopware\Core\Checkout\DocumentV2\AbstractDocumentRenderer;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentGenerationContext;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\RenderResult;
use Shopware\Core\Checkout\DocumentV2\RenderState;

/**
 * @internal
 */
#[Package('TODO')]
class InvoiceEmbeddedZugferdRenderer extends AbstractDocumentRenderer
{
    public const TYPE = DocumentType::Invoice->value;
    public const FORMAT = DocumentFormat::EmbeddedZugferd->value;

    public function getDocumentTypes(): array
    {
        return [self::TYPE];
    }

    public function getFormat(): string
    {
        return self::FORMAT;
    }

    public function getDependencies(): array
    {
        return [InvoicePdfRenderer::FORMAT, InvoiceZugferdXmlRenderer::FORMAT];
    }

    public function renderToString(DocumentGenerationContext $generationContext, RenderState $renderState): RenderResult
    {
        // TODO: Implement renderToString() method.
        $pdfResult = $renderState->getRenderedContent(InvoicePdfRenderer::FORMAT);
        $zugferdXmlResult = $renderState->getRenderedContent(InvoiceZugferdXmlRenderer::FORMAT);

        return new RenderResult($pdfResult->documentContent . $zugferdXmlResult->documentContent);
    }

    public function persistToFile(DocumentGenerationContext $generationContext, RenderResult $renderResult): string
    {
        // TODO: Implement persistToFile() method.
        return 'uuid-of-media-entity';
    }
}
