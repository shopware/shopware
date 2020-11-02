<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Discount\Filter;

use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountLineItem;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackageCollection;

class AdvancedPackagePicker
{
    /**
     * @var FilterServiceRegistry
     */
    private $registry;

    public function __construct(FilterServiceRegistry $registry)
    {
        $this->registry = $registry;
    }

    public function pickItems(DiscountLineItem $discount, DiscountPackageCollection $scopePackages): DiscountPackageCollection
    {
        $pickerKey = $discount->getFilterPickerKey();

        // we start by modifying our packages
        // with the currently set picker, if available
        // this restructures our packages
        if (!empty($pickerKey)) {
            $picker = $this->registry->getPicker($pickerKey);

            // get the new list of packages to consider
            $scopePackages = $picker->pickItems($scopePackages);
        }

        return $scopePackages;
    }
}
