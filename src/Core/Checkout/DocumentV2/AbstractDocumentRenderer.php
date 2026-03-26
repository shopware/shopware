<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
abstract class AbstractDocumentRenderer
{
    /**
     * All document types this renderer supports.
     *
     * @see DocumentType
     *
     * @return list<string> document types passed as strings
     */
    abstract public function getDocumentTypes(): array;

    /**
     * The format this renderer produces.
     *
     * @see DocumentFormat
     */
    abstract public function getFormat(): string;

    /**
     * Formats, see @DocumentFormat, this renderer has a dependency on.
     * e.g. ['html'] for PDF renderer that converts HTML → PDF.
     *
     * @return list<string> formats passed as strings
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
     * Render for a single specific document type + format and return the document as a string.
     * (registered) Dependencies can be retrieved from the @see RenderState
     */
    abstract public function renderToString(RenderInput $renderInput, RenderState $renderState): RenderResult;

    /**
     * Persist the rendered document to a file, returning its shopware media id.
     */
    abstract public function persistToFile(RenderInput $renderInput, RenderResult $renderResult): string;
}
