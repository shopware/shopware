<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Renderer;

use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderInput;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderResult;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderState;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * Transforms prepared RenderInput data into one concrete document format.
 *
 * Renderers can depend on other formats and consume their output from RenderState, which makes
 * chained generation flows like HTML -> PDF -> embedded PDF explicit in code.
 *
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
abstract readonly class AbstractDocumentRenderer
{
    /**
     * Returns the output format this renderer produces.
     *
     * @see DocumentFormat
     */
    abstract public function getFormat(): string;

    /**
     * Returns the document types this renderer can render.
     *
     * @see DocumentType
     *
     * @return list<string>
     */
    abstract public function getDocumentTypes(): array;

    /**
     * Returns whether this renderer can render the given document type.
     */
    public function supports(string $type): bool
    {
        return \in_array($type, $this->getDocumentTypes(), true);
    }

    /**
     * Returns prerequisite formats that must exist in RenderState before this renderer runs.
     *
     * @see DocumentFormat
     *
     * @return list<string>
     */
    public function getDependencies(): array
    {
        return [];
    }

    /**
     * Renders the format into memory without persisting it.
     */
    abstract public function renderToString(RenderInput $input, RenderState $state, Context $context): RenderResult;
}
