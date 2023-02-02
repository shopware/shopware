<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Discount\Filter\Sorter;

use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemQuantity;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemQuantityCollection;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackageCollection;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Filter\FilterSorterInterface;

class FilterSorterPriceDesc implements FilterSorterInterface
{
    public function getKey(): string
    {
        return 'PRICE_DESC';
    }

    public function sort(DiscountPackageCollection $packages): DiscountPackageCollection
    {
        foreach ($packages as $package) {
            /** @var array $metaItems */
            $metaItems = $package->getMetaData()->getElements();

            usort($metaItems, static function (LineItemQuantity $a, LineItemQuantity $b) use ($package) {
                // we only have meta data here
                // so lets get the prices
                $priceA = $package->getCartItem($a->getLineItemId())->getPrice();
                $priceB = $package->getCartItem($b->getLineItemId())->getPrice();

                if ($priceA === null) {
                    return 1;
                }

                if ($priceB === null) {
                    return 0;
                }

                return $priceB->getUnitPrice() <=> $priceA->getUnitPrice();
            });

            $package->setMetaItems(new LineItemQuantityCollection($metaItems));
        }

        return $packages;
    }
}
