<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Hydration\DataContext\Distribution\Config;

use Shopware\Core\Content\ContentSystem\Hydration\DataContext\DistributionStrategy;
use Shopware\Core\Framework\Log\Package;

/**
 * Configuration for iterator distribution strategy.
 *
 * Iterator repeats a template element for each item in a collection,
 * creating cloned elements dynamically.
 *
 * Used for: Dynamic lists with repeating templates
 * Example: Product listing where a product card template is repeated per product
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
