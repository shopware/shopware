<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Struct;

/**
 * @package core
 *
 * @deprecated tag:v6.5.0 will be removed, as it is not needed anymore
 */
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
