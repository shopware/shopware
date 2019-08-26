<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\LineItem\Group\Sorter;

use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupSorterInterface;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;

class LineItemGroupPriceDescSorter implements LineItemGroupSorterInterface
{
    public function getKey(): string
    {
        return 'PRICE_DESC';
    }

    public function sort(LineItemCollection $items): LineItemCollection
    {
        $listArray = $items->getElements();

        usort($listArray, function (LineItem $a, LineItem $b) {
            if ($a->getPrice() === null) {
                return true;
            }

            if ($b->getPrice() === null) {
                return false;
            }

            return $a->getPrice()->getUnitPrice() < $b->getPrice()->getUnitPrice();
        });

        return new LineItemCollection($listArray);
    }
}
