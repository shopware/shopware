<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Discount\Filter;

use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemQuantity;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemQuantityCollection;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountLineItem;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackage;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackageCollection;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Filter\Applier\Applier;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Filter\MaxUsage\MaxUsage;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

class AdvancedPackageFilter extends PackageFilter
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

    public function getDecorated(): PackageFilter
    {
        throw new DecorationPatternException(self::class);
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

    private function hasFilterSettings(string $sorterKey, string $applierKey, string $countKey): bool
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
