<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Discount\Filter\MaxUsage;

use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class MaxUsage
{
    final public const APPLIER_ALL = 'ALL';
    final public const USAGE_ALL = 'ALL';
    final public const UNLIMITED = -1;

    public function getMaxItemCount(string $applierKey, string $maxUsageKey, int $originalPackageCount): int
    {
        $maxItemUsages = self::UNLIMITED;

        if ($applierKey === self::APPLIER_ALL && $maxUsageKey === self::USAGE_ALL) {
            return $maxItemUsages;
        }

        // the applier key defines "what" item is being
        // used from a package.
        // thus our count is the number or original found packages
        // upt to a maximum usage count.
        $maxItemUsages = $originalPackageCount;

        // 1 item for every package with
        // unlimited repetitions so return.
        if ($maxUsageKey === self::USAGE_ALL) {
            return $maxItemUsages;
        }

        // check if we have a upper bound limitation using the usage key
        // e.g. 3 items would be discounted but we only allow 2
        if ((int) $maxUsageKey < $maxItemUsages) {
            $maxItemUsages = (int) $maxUsageKey;
        }

        // if less items would be discounted but we would allow more
        // this is only allowed if we have an original package count of 1
        // otherwise the count is always limited to 1 item per package
        if ($originalPackageCount === 1) {
            $maxItemUsages = (int) $maxUsageKey;
        }

        return $maxItemUsages;
    }
}
