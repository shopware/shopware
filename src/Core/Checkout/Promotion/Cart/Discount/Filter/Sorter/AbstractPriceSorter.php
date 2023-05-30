<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Discount\Filter\Sorter;

use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemQuantity;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemQuantityCollection;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackage;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackageCollection;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Filter\FilterSorterInterface;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
abstract class AbstractPriceSorter implements FilterSorterInterface
{
    public function sort(DiscountPackageCollection $packages): DiscountPackageCollection
    {
        foreach ($packages as $package) {
            /** @var array<LineItemQuantity> $metaItems */
            $metaItems = $package->getMetaData()->getElements();

            $metaItems = $this->_sort($metaItems, $package);

            $package->setMetaItems(new LineItemQuantityCollection($metaItems));
        }

        return $packages;
    }

    /**
     * @param array<string, LineItemQuantity[]> $map
     *
     * @return array<string, LineItemQuantity[]>
     */
    abstract protected function sortPriceMap(array $map): array;

    /**
     * @param array<LineItemQuantity> $metaItems
     *
     * @return array<LineItemQuantity>
     */
    private function _sort(array $metaItems, DiscountPackage $package): array
    {
        $priceMap = [];

        foreach ($metaItems as $item) {
            $price = $package->getCartItem($item->getLineItemId())->getPrice();

            // use 0 as default price
            $price = $price ? $price->getUnitPrice() : 0;

            // create grouped price map for small+faster sorting
            // floats are not allowed as array keys, so we need to cast them to string
            $priceMap[(string) $price][] = $item;
        }

        // @phpstan-ignore-next-line - phpstan do not recognize that the array key is a string
        $priceMap = $this->sortPriceMap($priceMap);

        $sorted = [];
        foreach ($priceMap as $items) {
            foreach ($items as $item) {
                $sorted[] = $item;
            }
        }

        return $sorted;
    }
}
