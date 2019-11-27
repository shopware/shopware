<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Discount\Filter;

use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackageCollection;

class AdvancedPackageFilter
{
    public const APPLIER_ALL = 'ALL';

    public const USAGE_ALL = 'ALL';

    /**
     * @var FilterServiceRegistry
     */
    private $registry;

    public function __construct(FilterServiceRegistry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * @throws Exception\FilterSorterNotFoundException
     */
    public function filter(string $sorterKey, string $applierKey, string $countKey, DiscountPackageCollection $scopePackages): DiscountPackageCollection
    {
        $filteredPackages = [];

        if (!$this->hasFilterSettings($sorterKey, $applierKey, $countKey)) {
            // no graduation settings
            // just add all items of all units
            foreach ($scopePackages as $package) {
                $filteredPackages[] = $package;
            }

            return new DiscountPackageCollection($filteredPackages);
        }

        // we have to start with index 1
        // otherwise every "2nd" unit would already start
        // with the first one due to the "0 modulo x".
        $index = 1;
        $discountedCount = 0;

        $sortedPackages = $this->registry->getSorter($sorterKey)->sort($scopePackages);

        foreach ($sortedPackages as $package) {
            // if we do not apply the graduation on
            // every unit, then make sure to use the
            // configuration by using every x units
            if ($applierKey !== self::APPLIER_ALL && $index % (int) $applierKey !== 0) {
                ++$index;

                continue;
            }

            // we use our unit,
            // so merge the items into the existing list
            $filteredPackages[] = $package;

            ++$index;
            ++$discountedCount;

            // if we dont repeat it with an unlimited account
            // check if we have reached our max-repeat limit and finish our resolving process.
            if ($countKey !== self::USAGE_ALL && (int) $countKey === $discountedCount) {
                break;
            }
        }

        return new DiscountPackageCollection($filteredPackages);
    }

    public function hasFilterSettings(string $sorterKey, string $applierKey, string $countKey): bool
    {
        if (empty($sorterKey)) {
            return false;
        }

        if (empty($applierKey)) {
            return false;
        }

        if (empty($countKey)) {
            return false;
        }

        return true;
    }
}
