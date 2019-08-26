<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\LineItem\Group\Packager;

use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupPackagerInterface;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class LineItemGroupUnitPriceNetPackager implements LineItemGroupPackagerInterface
{
    public function getKey(): string
    {
        return 'PRICE_UNIT_NET';
    }

    /**
     * This packager adds all items to a bundle, until the sum of their unit prices (gross)
     * reaches the provided minimum value for the package.
     *
     * @throws \Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException
     * @throws \Shopware\Core\Checkout\Cart\Exception\LineItemNotStackableException
     * @throws \Shopware\Core\Checkout\Cart\Exception\MixedLineItemTypeException
     */
    public function buildGroupPackage(float $minPackageValue, LineItemCollection $sortedItems, SalesChannelContext $context): LineItemCollection
    {
        $matchedItems = new LineItemCollection();

        $currentPackageSum = 0.0;

        /** @var LineItem $lineItem */
        foreach ($sortedItems as $lineItem) {
            if ($lineItem->getPrice() === null) {
                continue;
            }

            // add as long as the minimum package value is not reached
            if ($currentPackageSum >= $minPackageValue) {
                break;
            }

            $matchedItems->add($lineItem);

            /** @var float $grossPrice */
            $grossPrice = $lineItem->getPrice()->getUnitPrice();

            /** @var float $netPrice */
            $netPrice = $grossPrice - $lineItem->getPrice()->getCalculatedTaxes()->getAmount();

            $currentPackageSum += $lineItem->getQuantity() * $netPrice;
        }

        // if we have less results than our max value
        // return an empty list, because that is not a valid group
        if ($currentPackageSum < $minPackageValue) {
            return new LineItemCollection();
        }

        return $matchedItems;
    }
}
