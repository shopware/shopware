<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Hydration\DataContext\Distribution\Config;

use Shopware\Core\Content\ContentSystem\Hydration\DataContext\DistributionStrategy;
use Shopware\Core\Framework\Log\Package;

/**
 * Configuration for keyed distribution strategy.
 *
 * Keyed distributes collection items by matching keys - each child element
 * has a key property, and receives the collection item with that key.
 *
 * Used for: Named data slots
 * Example: Showcase with 'featured', 'related', 'bestseller' named slots
 *
 * @internal
 */
#[Package('discovery')]
readonly class KeyedDistributionConfig implements DistributionConfig
{
    public function __construct(
        public string $keyProperty = 'data_key'
    ) {
    }

    public function getStrategy(): DistributionStrategy
    {
        return DistributionStrategy::Keyed;
    }

    public function toArray(): array
    {
        return [
            'distribution' => 'keyed',
            'key_property' => $this->keyProperty,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            keyProperty: $data['key_property'] ?? 'data_key'
        );
    }
}
