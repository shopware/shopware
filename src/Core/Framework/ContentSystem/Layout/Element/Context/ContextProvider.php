<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Layout\Element\Context;

use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\DistributionConfig;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
final readonly class ContextProvider implements \JsonSerializable
{
    public function __construct(
        public ContextType $type,
        public DistributionConfig $distributionConfig
    ) {
    }

    /**
     * Flat wire shape: the type discriminator plus the distribution config spread in.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return ['type' => $this->type->value, ...$this->distributionConfig->toArray()];
    }
}
