<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2;

/**
 * @internal
 */
#[Package('TODO')]
abstract class AbstractDocumentRenderer
{
    /**
     * @see DocumentType
     */
    abstract public function getDocumentType(): string;

    /**
     * @see DocumentFormat
     */
    abstract public function getFormat(): string;

    /**
     * Formats (strings) this renderer depends on, within the same document type.
     * e.g. ['html'] for pdf renderer that converts HTML → PDF.
     *
     * @return list<string>
     */
    public function getDependencies(): array
    {
        return [];
    }

    /**
     * Render and return the document as string.
     * (registered) Dependencies can be retrieved from the @see RenderState
     */
    abstract public function renderToString(DocumentGenerationContext $documentContext, RenderState $renderState): string;

    abstract public function persistToFile(string $renderedContent): void;
}
