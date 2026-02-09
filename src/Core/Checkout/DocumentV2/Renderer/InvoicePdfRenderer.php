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
class InvoicePdfRenderer extends AbstractDocumentRenderer
{
    public const TYPE = DocumentType::Invoice->value;
    public const FORMAT = DocumentFormat::Pdf->value;

    public function getDocumentType(): string
    {
        return self::TYPE;
    }

    public function getFormat(): string
    {
        return self::FORMAT;
    }

    public function getDependencies(): array
    {
        return [InvoiceHtmlRenderer::FORMAT];
    }

    public function renderToString(DocumentGenerationContext $documentContext, RenderState $renderState): string
    {
        $htmlContent = $renderState->getRenderedContent(InvoiceHtmlRenderer::FORMAT);

        return $htmlContent . ' extend by pdf renderer';
    }

    public function persistToFile(string $renderedContent): void
    {
        // TODO: Implement persistToFile() method.
    }
}
