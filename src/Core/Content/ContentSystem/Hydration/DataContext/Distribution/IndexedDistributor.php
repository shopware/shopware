<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Hydration\DataContext\Distribution;

use Shopware\Core\Content\ContentSystem\Hydration\DataContext\DistributionStrategyInterface;
use Shopware\Core\Framework\Log\Package;

/**
 * Distributes collection items by position. Fewer items → consumers get null, more items → ignored.
 *
 * @internal
 */
#[Package('discovery')]
class IndexedDistributor implements DistributionStrategyInterface
{
    public function supports(string $distribution): bool
    {
        return $distribution === 'indexed';
    }

    /**
     * @return array<int, mixed>
     */
    public function distribute(mixed $data, array $consumers, array $config): array
    {
        if (!\is_array($data)) {
            return array_fill(0, \count($consumers), null);
        }

        $items = array_values($data);

        $result = [];
        foreach ($consumers as $index => $consumer) {
            $result[$index] = $items[$index] ?? null;
        }

        return $result;
    }
}
