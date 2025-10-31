<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Hydration\DataContext\Distribution\Config;

use Shopware\Core\Content\ContentSystem\Hydration\DataContext\DistributionStrategy;
use Shopware\Core\Framework\Log\Package;

/**
 * Clones template element for each collection item.
 *
 * @internal
 */
#[Package('discovery')]
readonly class IteratorDistributionConfig implements DistributionConfig
{
    public function __construct(
        public ?string $consumerAlias = null
    ) {
    }

    public function getStrategy(): DistributionStrategy
    {
        return DistributionStrategy::Iterator;
    }

    public function getConsumerAlias(): ?string
    {
        return $this->consumerAlias;
    }

    public function toArray(): array
    {
        return [
            'distribution' => 'iterator',
            'consumer_alias' => $this->consumerAlias,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            consumerAlias: $data['consumer_alias'] ?? null
        );
    }
}
