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
class InvoicePdfRenderer extends AbstractDocumentRenderer
{
    public const TYPE = DocumentType::Invoice->value;
    public const FORMAT = DocumentFormat::Pdf->value;

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
        return [HtmlRenderer::FORMAT];
    }

    public function renderToString(DocumentGenerationContext $generationContext, RenderState $renderState): RenderResult
    {
        $htmlResult = $renderState->getRenderedContent(HtmlRenderer::FORMAT);

        return new RenderResult($htmlResult->documentContent . ' extend by pdf renderer');
    }

    public function persistToFile(DocumentGenerationContext $generationContext, RenderResult $renderResult): string
    {
        // TODO: Implement persistToFile() method.
        return 'uuid-of-media-entity';
    }
}
