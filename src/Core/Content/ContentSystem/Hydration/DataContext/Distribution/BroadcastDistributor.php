<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Hydration\DataContext\Distribution;

use Shopware\Core\Content\ContentSystem\Hydration\DataContext\Distribution\Config\DistributionConfig;
use Shopware\Core\Content\ContentSystem\Hydration\DataContext\DistributionStrategyInterface;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @final
 */
#[Package('discovery')]
class BroadcastDistributor implements DistributionStrategyInterface
{
    public function supports(string $distribution): bool
    {
        return $distribution === 'broadcast';
    }

    /**
     * @return array<int, mixed>
     */
    public function distribute(mixed $data, array $consumers, DistributionConfig $config): array
    {
        return array_fill(0, \count($consumers), $data);
    }
}
