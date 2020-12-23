<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Discount\Filter;

use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemQuantity;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemQuantityCollection;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountLineItem;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackage;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackageCollection;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Filter\Applier\Applier;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Filter\MaxUsage\MaxUsage;

class AdvancedPackageFilter
{
    public const APPLIER_ALL = 'ALL';
    public const USAGE_ALL = 'ALL';
    public const UNLIMITED = -1;

    /**
     * @var FilterServiceRegistry
     */
    private $registry;

    public function __construct(FilterServiceRegistry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * @deprecated tag:v6.4.0 - use new filterPackages
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

    public function filterPackages(DiscountLineItem $discount, DiscountPackageCollection $packages, int $originalPackageCount): DiscountPackageCollection
    {
        $sorterKey = $discount->getFilterSorterKey();
        $applierKey = $discount->getFilterApplierKey();
        $countKey = $discount->getFilterUsageKey();

        $filteredPackages = [];

        if (!$this->hasFilterSettings($sorterKey, $applierKey, $countKey)) {
            return new DiscountPackageCollection($packages);
        }

        // now sort each found package depending on our configured sorter
        $sortedPackages = $this->registry->getSorter($sorterKey)->sort($packages);

        // calculate an additional maximal count
        // of items that need to be discounted
        $maxUsage = new MaxUsage();
        $maxItems = $maxUsage->getMaxItemCount($applierKey, $countKey, $originalPackageCount);

        // get all index entries for
        // the items that need to be discounted
        $applier = new Applier();
        $applierIndexes = $applier->findIndexes($applierKey, $maxItems, $sortedPackages->count(), $originalPackageCount);

        $discountedItems = 0;

        foreach ($sortedPackages as $package) {
            // filter and collect the items of our
            // current package and add the matching items to our list
            $items = $this->collectPackageItems($package, $applierIndexes);

            // now iterate through all items
            // and make sure we only add the collected items
            // up to a maximum count depending on our settings
            $addItems = new LineItemQuantityCollection();

            foreach ($items as $item) {
                if ($maxItems > self::UNLIMITED && $discountedItems >= $maxItems) {
                    break;
                }

                $addItems->add($item);
                ++$discountedItems;
            }

            if ($addItems->count() <= 0) {
                continue;
            }

            $filteredPackages[] = new DiscountPackage($addItems);
        }

        return new DiscountPackageCollection($filteredPackages);
    }

    /**
     * @deprecated tag:v6.4.0 - visibility will be set to private in shopware 6.4.0, do not access it by any other class any more
     */
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

    private function collectPackageItems(DiscountPackage $package, array $applierIndexes): LineItemQuantityCollection
    {
        $items = new LineItemQuantityCollection();

        // we start with -1
        // because it will immediately move to 0
        // and the code is cleaner
        $index = -1;

        foreach ($package->getMetaData() as $item) {
            ++$index;

            // if our indexes are empty, then
            // we use all items, otherwise do only use
            // the items of our pre calculated indexes
            if (!empty($applierIndexes) && !\in_array($index, $applierIndexes, true)) {
                continue;
            }

            $items->add(new LineItemQuantity($item->getLineItemId(), $item->getQuantity()));
        }

        return $items;
    }
}
