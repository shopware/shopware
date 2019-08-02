<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\LineItem\Group\Sorter;

use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupSorterInterface;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;

class LineItemGroupPriceAscSorter implements LineItemGroupSorterInterface
{
    public function getKey(): string
    {
        return 'PRICE_ASC';
    }

    public function sort(LineItemCollection $items): LineItemCollection
    {
        $listArray = $items->getElements();

        usort($listArray, function (LineItem $a, LineItem $b) {
            if ($a->getPrice() === null) {
                return false;
            }

            if ($b->getPrice() === null) {
                return true;
            }

            return $a->getPrice()->getUnitPrice() > $b->getPrice()->getUnitPrice();
        });

        return new LineItemCollection($listArray);
    }
}
