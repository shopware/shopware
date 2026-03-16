<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Renderer;

use Shopware\Core\Checkout\DocumentV2\AbstractDocumentRenderer;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentGenerationContext;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\RenderResult;
use Shopware\Core\Checkout\DocumentV2\RenderState;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

/**
 * @internal
 */
#[Package('TODO')]
class HtmlRenderer extends AbstractDocumentRenderer
{
    public const FORMAT = DocumentFormat::Html->value;

    public function getDocumentTypes(): array
    {
        return [
            DocumentType::Invoice->value,
            DocumentType::CreditNote->value,
        ];
    }

    public function getFormat(): string
    {
        return self::FORMAT;
    }

    public function enrichOrderCriteria(string $docType, Criteria $criteria): void
    {
        if ($docType === DocumentType::CreditNote->value) {
            // do something different
        }

        $criteria->addAssociation('lineItems');
    }

    public function renderToString(DocumentGenerationContext $generationContext, RenderState $renderState): RenderResult
    {
        // TODO: Implement renderToString() method.

        $template = match ($generationContext->documentType) {
            DocumentType::Invoice->value => 'invoice twig template',
            DocumentType::CreditNote->value => 'credit note twig template',
            default => throw new \InvalidArgumentException('Unsupported document type: ' . $generationContext->documentType),
        };

        // use some document config
        $pageOrientation = $generationContext->documentConfig->extensions['pageOrientation'] ?? 'portrait';
        // also possible to have a specific struct under extensions

        // use some order data
        $lineItem = $generationContext->order->getLineItems()?->first();

        return new RenderResult($template . $lineItem?->getLabel());
    }

    public function persistToFile(DocumentGenerationContext $generationContext, RenderResult $renderResult): string
    {
        // TODO: Implement persistToFile() method.
        return 'uuid-of-media-entity';
    }
}
