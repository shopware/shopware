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
final readonly class IteratorDistributionConfig implements DistributionConfig
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
}
