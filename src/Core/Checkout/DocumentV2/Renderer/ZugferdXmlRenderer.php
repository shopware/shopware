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
class ZugferdXmlRenderer extends AbstractDocumentRenderer
{
    public const FORMAT = DocumentFormat::ZugferdXml->value;

    public function supports(string $docType): bool
    {
        // todo: think about if it makes sense to check based on if there is a twig template for the doc type
        return $docType === DocumentType::Invoice->value
            || $docType === DocumentType::CancellationInvoice->value
            || $docType === DocumentType::CreditNote->value;
    }

    public function getFormat(): string
    {
        return self::FORMAT;
    }

    public function renderToString(RenderInput $renderInput, RenderState $renderState): RenderResult
    {
        // todo: also render with twig

        return new RenderResult('<zugferd xml/>');
    }

    public function persistToFile(RenderInput $renderInput, RenderResult $renderResult): string
    {
        // TODO: Implement persistToFile() method.
        return 'uuid-of-media-entity';
    }
}
