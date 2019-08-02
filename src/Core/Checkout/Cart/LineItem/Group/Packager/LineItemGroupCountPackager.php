<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\LineItem\Group\Packager;

use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupPackagerInterface;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class LineItemGroupCountPackager implements LineItemGroupPackagerInterface
{
    public function getKey(): string
    {
        return 'COUNT';
    }

    /**
     * This packager builds a bundle for the first x items
     * until the maximum number of items in the group is reached.
     * If not enough items are found to fill the group, then an empty list will be returned.
     *
     * @throws \Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException
     * @throws \Shopware\Core\Checkout\Cart\Exception\LineItemNotStackableException
     * @throws \Shopware\Core\Checkout\Cart\Exception\MixedLineItemTypeException
     */
    public function buildGroupPackage(float $maxItemsInGroup, LineItemCollection $sortedItems, SalesChannelContext $context): LineItemCollection
    {
        $matchedItems = new LineItemCollection();

        $matchedCount = 0;

        /** @var LineItem $lineItem */
        foreach ($sortedItems as $lineItem) {
            /** @var int $quantity */
            $quantity = $lineItem->getQuantity();

            $matchedItems->add($lineItem);
            $matchedCount += $quantity;

            // as long as we have not filled our maximum count
            // add all items that match our group rules
            if ($matchedCount >= $maxItemsInGroup) {
                break;
            }
        }

        // if we have less results than our max items
        // return an empty list, because that is not a valid group
        if ($matchedCount < $maxItemsInGroup) {
            return new LineItemCollection();
        }

        return $matchedItems;
    }
}
