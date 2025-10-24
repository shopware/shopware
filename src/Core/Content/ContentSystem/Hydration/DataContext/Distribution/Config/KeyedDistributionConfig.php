<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Hydration\DataContext\Distribution\Config;

use Shopware\Core\Content\ContentSystem\Hydration\DataContext\DistributionStrategy;
use Shopware\Core\Framework\Log\Package;

/**
 * Distributes collection items by matching child element key property.
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
