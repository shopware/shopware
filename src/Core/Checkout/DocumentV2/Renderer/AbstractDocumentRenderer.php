<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Renderer;

use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderInput;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderResult;
use Shopware\Core\Checkout\DocumentV2\Struct\RenderState;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
abstract class AbstractDocumentRenderer
{
    /**
     * @see DocumentType
     */
    abstract public function supports(string $type): bool;

    /**
     * @see DocumentFormat
     */
    abstract public function getFormat(): string;

    /**
     * @see DocumentFormat
     *
     * @return list<string>
     */
    public function getDependencies(): array
    {
        return [];
    }

    abstract public function renderToString(RenderInput $input, RenderState $state): RenderResult;

    abstract public function persistToFile(RenderInput $input, RenderState $state): string;
}
