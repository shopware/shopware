<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Renderer;

use Shopware\Core\Checkout\DocumentV2\AbstractDocumentRenderer;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentGenerationContext;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
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

    public function renderToString(DocumentGenerationContext $documentContext, RenderState $renderState): string
    {
        // TODO: Implement renderToString() method.
        $pdfContent = $renderState->getRenderedContent(InvoicePdfRenderer::FORMAT);
        $zugferdXmlContent = $renderState->getRenderedContent(InvoiceZugferdXmlRenderer::FORMAT);

        return $pdfContent . $zugferdXmlContent;
    }

    public function persistToFile(string $renderedContent): void
    {
        // TODO: Implement persistToFile() method.
    }
}
