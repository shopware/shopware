<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Discount\Filter\Sorter;

use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemQuantity;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class FilterSorterPriceAsc extends AbstractPriceSorter
{
    public function getKey(): string
    {
        return 'PRICE_ASC';
    }

    /**
     * @param array<string, LineItemQuantity[]> $map
     *
     * @return array<string, LineItemQuantity[]>
     */
    protected function sortPriceMap(array $map): array
    {
        \uksort($map, static function (string $a, string $b) {
            // the prices are stored as strings, so we need to cast them to float
            // we need to store the prices as string, because you can not use floats as array keys
            return (float) $a <=> (float) $b;
        });

        return $map;
    }
}
