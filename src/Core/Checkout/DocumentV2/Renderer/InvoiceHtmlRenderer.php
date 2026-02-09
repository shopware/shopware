<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Renderer;

use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\AbstractDocumentRenderer;
use Shopware\Core\Checkout\DocumentV2\DocumentGenerationContext;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\RenderState;

/**
 * @internal
 */
#[Package('TODO')]
class InvoiceHtmlRenderer extends AbstractDocumentRenderer
{
    public const TYPE = DocumentType::Invoice->value;
    public const FORMAT = DocumentFormat::Html->value;

    public function getDocumentType(): string
    {
        return self::TYPE;
    }

    public function getFormat(): string
    {
        return self::FORMAT;
    }

    public function renderToString(DocumentGenerationContext $documentContext, RenderState $renderState): string
    {
        // TODO: Implement renderToString() method.
        return 'invoice html content';
    }

    public function persistToFile(string $renderedContent): void
    {
        // TODO: Implement persistToFile() method.
    }
}
