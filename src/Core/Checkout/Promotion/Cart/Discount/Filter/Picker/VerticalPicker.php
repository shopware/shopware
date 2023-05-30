<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Discount\Filter\Picker;

use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackageCollection;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Filter\FilterPickerInterface;
use Shopware\Core\Framework\Log\Package;

/**
 * The vertical picker makes sure that the filter
 * iteration is taking place within each group.
 * So if you decide to get the first 2 cheapest items,
 * then it will return the first 2 cheapest items from each group.
 */
#[Package('checkout')]
class VerticalPicker implements FilterPickerInterface
{
    public function getKey(): string
    {
        return 'VERTICAL';
    }

    public function pickItems(DiscountPackageCollection $units): DiscountPackageCollection
    {
        return new DiscountPackageCollection($units);
    }
}
