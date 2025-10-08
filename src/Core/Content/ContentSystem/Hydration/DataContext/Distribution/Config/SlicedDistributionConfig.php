<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Hydration\DataContext\Distribution\Config;

use Shopware\Core\Content\ContentSystem\Hydration\DataContext\DistributionStrategy;
use Shopware\Core\Framework\Log\Package;

/**
 * Configuration for sliced distribution strategy.
 *
 * Sliced divides a collection into chunks and distributes each chunk
 * to a different child element.
 *
 * Used for: Multi-row/column layouts
 * Example: Gallery with 3 rows, each receiving 4 products (slice_size=4)
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
