<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Struct;

use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.5.0 will be removed, as it is not needed anymore
 */
#[Package('core')]
trait SortArrayByKeysTrait
{
    /**
     * @param int[]|array<string> $sortedKeys
     * @param array          $indexedArray - indexed with keys
     */
    protected function sortIndexedArrayByKeys(array $sortedKeys, array $indexedArray): array
    {
        $sorted = [];
        foreach ($sortedKeys as $index) {
            if (\array_key_exists($index, $indexedArray)) {
                $sorted[$index] = $indexedArray[$index];
            }
        }

        return $sorted;
    }
}
