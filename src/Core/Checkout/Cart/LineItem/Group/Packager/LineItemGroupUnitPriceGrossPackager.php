<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\LineItem\Group\Packager;

use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroup;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupPackagerInterface;
use Shopware\Core\Checkout\Cart\LineItem\LineItemFlatCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class LineItemGroupUnitPriceGrossPackager implements LineItemGroupPackagerInterface
{
    public function getKey(): string
    {
        return 'PRICE_UNIT_GROSS';
    }

    /**
     * This packager adds all items to a bundle, until the sum of their item prices (gross)
     * reaches the provided minimum value for the package.
     *
     * @throws \Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException
     * @throws \Shopware\Core\Checkout\Cart\Exception\LineItemNotStackableException
     * @throws \Shopware\Core\Checkout\Cart\Exception\MixedLineItemTypeException
     */
    public function buildGroupPackage(float $minPackageValue, LineItemFlatCollection $sortedItems, SalesChannelContext $context): LineItemGroup
    {
        $result = new LineItemGroup();

        $currentPackageSum = 0.0;

        foreach ($sortedItems as $lineItem) {
            if ($lineItem->getPrice() === null) {
                continue;
            }

            // add as long as the minimum package value is not reached
            if ($currentPackageSum >= $minPackageValue) {
                break;
            }

            // add the item to our result
            // with the current quantity
            $result->addItem($lineItem->getId(), $lineItem->getQuantity());

            /** @var CalculatedPrice $price */
            $price = $lineItem->getPrice();

            $grossPrice = $price->getUnitPrice();

            $currentPackageSum += $lineItem->getQuantity() * $grossPrice;
        }

        // if we have less results than our max value
        // return an empty list, because that is not a valid group
        if ($currentPackageSum < $minPackageValue) {
            return new LineItemGroup();
        }

        return $result;
    }
}
