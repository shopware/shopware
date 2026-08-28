<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Type;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
abstract readonly class AbstractDocumentType
{
    abstract public function getTechnicalName(): string;

    /**
     * The document formats offered as selectable outputs for this type.
     *
     * @return list<string>
     */
    abstract public function getSupportedFormats(): array;

    public function allowsNegativeLineItems(): bool
    {
        return false;
    }
}
