<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\LineItem\Group\Sorter;

use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupSorterInterface;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemFlatCollection;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class LineItemGroupPriceAscSorter implements LineItemGroupSorterInterface
{
    public function getKey(): string
    {
        return 'PRICE_ASC';
    }

    public function sort(LineItemFlatCollection $items): LineItemFlatCollection
    {
        $sorted = $items->getElements();

        usort($sorted, static function (LineItem $a, LineItem $b) {
            if ($a->getPrice() === null) {
                return 0;
            }

            if ($b->getPrice() === null) {
                return 1;
            }

            return $a->getPrice()->getUnitPrice() <=> $b->getPrice()->getUnitPrice();
        });

        return new LineItemFlatCollection($sorted);
    }
}
