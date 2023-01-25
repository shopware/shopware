<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Discount\Filter\Picker;

use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemQuantityCollection;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackage;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackageCollection;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Filter\FilterPickerInterface;
use Shopware\Core\Framework\Log\Package;

/**
 * The horizontal picker makes sure that the filter
 * iteration is taking place across all groups.
 * So if we have 3 groups with 3 items (9 items), and
 * take the first 2 cheapest one, the picker
 * will make sure that it gets the 2 cheapest out of all 9 items.
 */
#[Package('checkout')]
class HorizontalPicker implements FilterPickerInterface
{
    public function getKey(): string
    {
        return 'HORIZONTAL';
    }

    /**
     * This picker returns a single package with all items
     * within this package.
     * So all packages are moved to a single package. And all
     * items are considered as if they would be in a single unit.
     */
    public function pickItems(DiscountPackageCollection $units): DiscountPackageCollection
    {
        $items = new LineItemQuantityCollection();

        foreach ($units as $unit) {
            foreach ($unit->getMetaData() as $item) {
                $items->add($item);
            }
        }

        $package = new DiscountPackage($items);

        return new DiscountPackageCollection([$package]);
    }
}
