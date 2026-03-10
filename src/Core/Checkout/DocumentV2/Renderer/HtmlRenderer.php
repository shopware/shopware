<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Renderer;

use Shopware\Core\Checkout\DocumentV2\AbstractDocumentRenderer;
use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentGenerationContext;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
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

    public function renderToString(DocumentGenerationContext $documentContext, RenderState $renderState): string
    {
        // TODO: Implement renderToString() method.

        $template = match ($documentContext->documentType) {
            DocumentType::Invoice->value => 'invoice twig template',
            DocumentType::CreditNote->value => 'credit note twig template',
            default => throw new \InvalidArgumentException('Unsupported document type: ' . $documentContext->documentType),
        };

        // use some document config
        $pageOrientation = $documentContext->documentConfig->extensions['pageOrientation'] ?? 'portrait';
        // also possible to have a specific struct under extensions

        // use some order data
        $lineItem = $documentContext->order->getLineItems()?->first();

        return $template . $lineItem?->getLabel();
    }

    public function persistToFile(string $renderedContent): void
    {
        // TODO: Implement persistToFile() method.
    }
}
