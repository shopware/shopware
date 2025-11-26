<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Hydration\DataContext\Distribution;

use Shopware\Core\Content\ContentSystem\Hydration\DataContext\DistributionStrategyInterface;
use Shopware\Core\Framework\Log\Package;

/**
 * Matches items by consumer 'data_key' property. Missing keys → null.
 *
 * @internal
 *
 * @final
 */
#[Package('discovery')]
class KeyedDistributor implements DistributionStrategyInterface
{
    public function supports(string $distribution): bool
    {
        return $distribution === 'keyed';
    }

    /**
     * @return array<int, mixed>
     */
    public function distribute(mixed $data, array $consumers, array $config): array
    {
        if (!\is_array($data)) {
            return array_fill(0, \count($consumers), null);
        }

        $result = [];
        foreach ($consumers as $index => $consumer) {
            $dataKey = $consumer['data_key'] ?? null;

            if ($dataKey === null) {
                $result[$index] = null;
                continue;
            }

            $result[$index] = $data[$dataKey] ?? null;
        }

        return $result;
    }
}
