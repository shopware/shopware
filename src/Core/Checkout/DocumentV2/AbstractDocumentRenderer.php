<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

/**
 * @internal
 */
#[Package('TODO')]
abstract class AbstractDocumentRenderer
{
    /**
     * @see DocumentType
     *
     * @return list<string>
     */
    abstract public function getDocumentTypes(): array;

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
     * Enrich order criteria with additional associations
     */
    public function enrichOrderCriteria(string $docType, Criteria $criteria): void
    {
        // nothing by default
    }

    /**
     * Render and return the document as string.
     * (registered) Dependencies can be retrieved from the @see RenderState
     */
    abstract public function renderToString(DocumentGenerationContext $documentContext, RenderState $renderState): string;

    abstract public function persistToFile(string $renderedContent): void;
}
