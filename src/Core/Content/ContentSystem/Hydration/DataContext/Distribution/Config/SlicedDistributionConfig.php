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
}
