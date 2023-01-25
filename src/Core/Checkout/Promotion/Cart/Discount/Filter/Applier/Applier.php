<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Discount\Filter\Applier;

use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class Applier
{
    final public const APPLIER_ALL = 'ALL';

    final public const UNLIMITED = -1;

    /**
     * Returns a list of index offsets for
     * all items that need to be considered for the discount
     */
    public function findIndexes(string $applierKey, int $maxItems, int $packageCount, int $originalPackageCount): array
    {
        $applierIndexes = [];

        if ($applierKey === self::APPLIER_ALL && $maxItems === self::UNLIMITED) {
            return $applierIndexes;
        }

        // no picker did change anything so we discount the contents of the packages.
        // this is just 1 single index for that 1 single item.
        // if we have an package count of 1, we always count on item base.
        if ($originalPackageCount === $packageCount && $originalPackageCount !== 1) {
            $applierIndexes[] = (int) $applierKey - 1;

            return $applierIndexes;
        }

        $packagePerItem = 1;

        if ($originalPackageCount === 1) {
            $packagePerItem = $maxItems;
        }

        // if a picker did change the number of packages,
        // then we calculated the number of affected items.
        // we use an offset. so an applier for the 2nd result means
        // the 2nd "package".
        // we just move to that offset and add indexes for every item in that package.
        $startIndex = (((int) $applierKey) - 1) * $originalPackageCount;

        // if we have unlimited appliers but a maxItem, then
        // our ALL applier would lead to -1, (we just start with the first item)
        if ($startIndex < 0) {
            $startIndex = 0;
        }

        $itemsToUse = $originalPackageCount * $packagePerItem;

        if ($maxItems !== self::UNLIMITED && $itemsToUse > $maxItems) {
            $itemsToUse = $maxItems;
        }

        $endIndex = $startIndex + $itemsToUse;

        for ($i = $startIndex; $i < $endIndex; ++$i) {
            $applierIndexes[] = $i;
        }

        return $applierIndexes;
    }
}
