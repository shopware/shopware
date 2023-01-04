<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Discount\Filter\Picker;

use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemQuantityCollection;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackage;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackageCollection;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Filter\FilterPickerInterface;
use Shopware\Core\Framework\Log\Package;

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
