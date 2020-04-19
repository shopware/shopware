<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Struct;

trait SortArrayByKeysTrait
{
    /**
     * @param int[]|string[] $sortedKeys
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
