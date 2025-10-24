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
    public function getStrategy(): DistributionStrategy
    {
        return DistributionStrategy::Iterator;
    }

    public function toArray(): array
    {
        return ['distribution' => 'iterator'];
    }

    public static function fromArray(array $data): self
    {
        return new self();
    }
}
