<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Discount\Filter\Sorter;

use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackage;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackageCollection;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Filter\FilterSorterInterface;

class FilterSorterPriceAsc implements FilterSorterInterface
{
    public function getKey(): string
    {
        return 'PRICE_ASC';
    }

    public function sort(DiscountPackageCollection $units): DiscountPackageCollection
    {
        $sorted = $units->getElements();

        usort($sorted, function (DiscountPackage $a, DiscountPackage $b) {
            return $a->getTotalPrice() > $b->getTotalPrice();
        });

        return new DiscountPackageCollection($sorted);
    }
}
