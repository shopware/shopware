<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Hydration\DataContext\Distribution\Config;

use Shopware\Core\Content\ContentSystem\Hydration\DataContext\DistributionStrategy;
use Shopware\Core\Framework\Log\Package;

/**
 * Divides collection into chunks distributed to child elements.
 *
 * @internal
 */
#[Package('discovery')]
readonly class SlicedDistributionConfig implements DistributionConfig
{
    public function __construct(
        public int $sliceSize
    ) {
    }

    public function getStrategy(): DistributionStrategy
    {
        return DistributionStrategy::Sliced;
    }

    public function toArray(): array
    {
        return [
            'distribution' => 'sliced',
            'slice_size' => $this->sliceSize,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            sliceSize: $data['slice_size'] ?? 10
        );
    }
}
