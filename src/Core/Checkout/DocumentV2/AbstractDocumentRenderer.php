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
     * If the renderer supports a specific document type.
     *
     * @see DocumentType
     */
    abstract public function supports(string $docType): bool;

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
     * Render for a single specific document type + format and return the document as a string.
     * (registered) Dependencies can be retrieved from the @see RenderState
     */
    abstract public function renderToString(RenderInput $renderInput, RenderState $renderState): RenderResult;

    /**
     * Persist the rendered document to a file, returning its shopware media id.
     */
    abstract public function persistToFile(RenderInput $renderInput, RenderResult $renderResult): string;
}
