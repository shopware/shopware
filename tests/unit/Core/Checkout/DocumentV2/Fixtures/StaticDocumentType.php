<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Fixtures;

use Shopware\Core\Checkout\DocumentV2\Type\AbstractDocumentType;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
final readonly class StaticDocumentType extends AbstractDocumentType
{
    /**
     * @param list<string> $supportedFormats
     */
    public function __construct(
        private string $technicalName,
        private array $supportedFormats,
    ) {
    }

    public function getTechnicalName(): string
    {
        return $this->technicalName;
    }

    public function getSupportedFormats(): array
    {
        return $this->supportedFormats;
    }
}
