<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Hydration\DataContext\Distribution\Config;

use Shopware\Core\Content\ContentSystem\Hydration\DataContext\DistributionStrategy;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
final readonly class SlicedDistributionConfig implements DistributionConfig
{
    public function __construct(
        public int $sliceSize,
        public ?string $consumerAlias = null
    ) {
    }

    public function getStrategy(): DistributionStrategy
    {
        return DistributionStrategy::Sliced;
    }

    public function getConsumerAlias(): ?string
    {
        return $this->consumerAlias;
    }

    public function toArray(): array
    {
        return [
            'distribution' => 'sliced',
            'slice_size' => $this->sliceSize,
            'consumer_alias' => $this->consumerAlias,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            sliceSize: $data['slice_size'] ?? 10,
            consumerAlias: $data['consumer_alias'] ?? null
        );
    }
}
