<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Hydration\DataContext\Distribution;

use Shopware\Core\Content\ContentSystem\Hydration\DataContext\DistributionStrategyInterface;
use Shopware\Core\Framework\Log\Package;

/**
 * Signals DataContextResolver to clone template element per collection item.
 * Unlike other strategies, this modifies element tree structure.
 *
 * @internal
 *
 * @final
 */
#[Package('discovery')]
class IteratorDistributor implements DistributionStrategyInterface
{
    public function supports(string $distribution): bool
    {
        return $distribution === 'iterator';
    }

    /**
     * Returns raw collection items as cloning happens during tree traversal in DataContextResolver.
     *
     * @return array<int, mixed>
     */
    public function distribute(mixed $data, array $consumers, array $config): array
    {
        if (!\is_array($data)) {
            return [];
        }

        return array_values($data);
    }
}
