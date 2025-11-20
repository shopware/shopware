<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Hydration\DataContext\Distribution;

use Shopware\Core\Content\ContentSystem\Hydration\DataContext\DistributionStrategyInterface;
use Shopware\Core\Framework\Log\Package;

/**
 * Chunks collection by slice_size. Last slice gets remainder if uneven.
 *
 * @internal
 */
#[Package('discovery')]
class SlicedDistributor implements DistributionStrategyInterface
{
    public function supports(string $distribution): bool
    {
        return $distribution === 'sliced';
    }

    /**
     * @return array<int, mixed>
     */
    public function distribute(mixed $data, array $consumers, array $config): array
    {
        if (!\is_array($data)) {
            return array_fill(0, \count($consumers), []);
        }

        $sliceSize = (int) ($config['slice_size'] ?? 1);

        if ($sliceSize < 1) {
            $sliceSize = 1;
        }

        $items = array_values($data);

        $slices = array_chunk($items, $sliceSize);

        $result = [];
        foreach ($consumers as $index => $consumer) {
            $result[$index] = $slices[$index] ?? [];
        }

        return $result;
    }
}
