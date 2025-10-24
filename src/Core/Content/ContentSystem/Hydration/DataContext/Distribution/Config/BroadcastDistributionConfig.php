<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Hydration\DataContext\Distribution\Config;

use Shopware\Core\Content\ContentSystem\Hydration\DataContext\DistributionStrategy;
use Shopware\Core\Framework\Log\Package;

/**
 * Distributes single entity to all child elements.
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
