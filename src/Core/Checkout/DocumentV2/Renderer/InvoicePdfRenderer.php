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

    public function renderToString(RenderInput $renderInput, RenderState $renderState): RenderResult
    {
        $htmlResult = $renderState->getRenderedContent(HtmlRenderer::FORMAT);
        if (!$htmlResult instanceof RenderResult) {
            // todo: error handling
            throw new \RuntimeException('Missing html renderer result');
        }

        // todo: do domPDF stuff here
        return new RenderResult('<pdf>' . $htmlResult->documentContent . '</pdf>');
    }

    public function persistToFile(RenderInput $renderInput, RenderResult $renderResult): string
    {
        // TODO: Implement persistToFile() method.
        return 'uuid-of-media-entity';
    }
}
