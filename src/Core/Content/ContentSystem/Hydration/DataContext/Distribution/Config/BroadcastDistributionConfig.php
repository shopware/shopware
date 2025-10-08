<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Hydration\DataContext\Distribution\Config;

use Shopware\Core\Content\ContentSystem\Hydration\DataContext\DistributionStrategy;
use Shopware\Core\Framework\Log\Package;

/**
 * Configuration for broadcast distribution strategy.
 *
 * Broadcast distributes a single entity to all child elements.
 * This is the simplest distribution pattern with no additional configuration.
 *
 * Used for: Detail pages where all children need the same entity
 * Example: Product detail page where header, gallery, info all need the same product
 *
 * @internal
 */
#[Package('discovery')]
readonly class BroadcastDistributionConfig implements DistributionConfig
{
    public function getStrategy(): DistributionStrategy
    {
        return DistributionStrategy::Broadcast;
    }

    public function toArray(): array
    {
        return ['distribution' => 'broadcast'];
    }

    public static function fromArray(array $data): self
    {
        return new self();
    }
}
