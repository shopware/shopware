<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Hydration\DataContext\Distribution\Config;

use Shopware\Core\Content\ContentSystem\Hydration\DataContext\DistributionStrategy;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
final readonly class KeyedDistributionConfig implements DistributionConfig
{
    public function __construct(
        public string $keyProperty = 'data_key',
        public ?string $consumerAlias = null
    ) {
    }

    public function getStrategy(): DistributionStrategy
    {
        return DistributionStrategy::Keyed;
    }

    public function getConsumerAlias(): ?string
    {
        return $this->consumerAlias;
    }

    public function toArray(): array
    {
        return [
            'distribution' => 'keyed',
            'key_property' => $this->keyProperty,
            'consumer_alias' => $this->consumerAlias,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            keyProperty: $data['key_property'] ?? 'data_key',
            consumerAlias: $data['consumer_alias'] ?? null
        );
    }
}
