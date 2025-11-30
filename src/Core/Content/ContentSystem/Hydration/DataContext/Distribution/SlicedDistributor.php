<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Hydration\DataContext\Distribution;

use Shopware\Core\Content\ContentSystem\Hydration\DataContext\Distribution\Config\DistributionConfig;
use Shopware\Core\Content\ContentSystem\Hydration\DataContext\Distribution\Config\SlicedDistributionConfig;
use Shopware\Core\Content\ContentSystem\Hydration\DataContext\DistributionStrategyInterface;
use Shopware\Core\Framework\Log\Package;

/**
 * Chunks collection by slice_size. Last slice gets remainder if uneven.
 *
 * @internal
 *
 * @final
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
    public function distribute(mixed $data, array $consumers, DistributionConfig $config): array
    {
        if (!\is_array($data)) {
            return array_fill(0, \count($consumers), []);
        }

        $sliceSize = 1;
        if ($config instanceof SlicedDistributionConfig) {
            $sliceSize = $config->sliceSize;
        }

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
