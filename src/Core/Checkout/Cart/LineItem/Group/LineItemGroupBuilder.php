<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\LineItem\Group;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItemFlatCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItemQuantitySplitter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
class LineItemGroupBuilder
{
    /**
     * @internal
     */
    public function __construct(
        private readonly LineItemGroupServiceRegistry $registry,
        private readonly LineItemGroupRuleMatcherInterface $ruleMatcher,
        private readonly LineItemQuantitySplitter $quantitySplitter,
        private readonly AbstractProductLineItemProvider $lineItemProvider
    ) {
    }

    /**
     * Searches for all packages that can be built from the provided list of groups.
     * Every line item will be taken from the cart and only the ones that are left will
     * be checked for upcoming groups.
     *
     * @param LineItemGroupDefinition[] $groupDefinitions
     */
    public function findGroupPackages(array $groupDefinitions, Cart $cart, SalesChannelContext $context): LineItemGroupBuilderResult
    {
        $result = new LineItemGroupBuilderResult();

        // filter out all promotion items
        $cartProducts = $this->lineItemProvider->getProducts($cart);

        // split quantities into separate line items
        // so we have a real list of products like we would have
        // them when holding it in our actual hands.
        $restOfCart = $this->splitQuantities($cartProducts, $context);

        foreach ($groupDefinitions as $groupDefinition) {
            $sorter = $this->registry->getSorter($groupDefinition->getSorterKey());
            $packager = $this->registry->getPackager($groupDefinition->getPackagerKey());

            // we have to sort our items first
            // otherwise it would be a "random" order when
            // adjusting the rest of our cart...
            $restOfCart = $sorter->sort($restOfCart);

            // try as long as groups can be
            // found for the current definition
            while (true) {
                $itemsToConsider = $this->ruleMatcher->getMatchingItems($groupDefinition, $restOfCart, $context);

                // now build a package with our packager
                $group = $packager->buildGroupPackage($groupDefinition->getValue(), $itemsToConsider, $context);

                // if we have no found items in our group, quit
                if (!$group->hasItems()) {
                    break;
                }

                // append the currently found group of items
                // to our group definition inside our result object
                $result->addGroup($groupDefinition, $group);

                // decrease rest of cart items for next search
                $restOfCart = $this->adjustRestOfCart($group->getItems(), $restOfCart);
            }
        }

        return $result;
    }

    /**
     * This is a very important function.
     * It removes our line items that are found in the group and returns the rest of the cart items.
     * So if we have 4 line items of 2 products with each quantity 1, and want to remove a product with qt 2,
     * then 2 line items will be removed and the new rest of the cart is being returned.
     *
     * @param LineItemQuantity[] $foundItems
     */
    private function adjustRestOfCart(array $foundItems, LineItemFlatCollection $restOfCart): LineItemFlatCollection
    {
        // a holder for all foundItems indexed by lineItemId
        /** @var LineItemQuantity[] $lineItemsToRemove */
        $lineItemsToRemove = [];

        // we prepare the removeLineItemIds array with all LineItemQuantity objects indexed by lineItemId
        foreach ($foundItems as $itemToRemove) {
            if (isset($lineItemsToRemove[$itemToRemove->getLineItemId()])) {
                $quantity = $lineItemsToRemove[$itemToRemove->getLineItemId()];
                $lineItemsToRemove[$itemToRemove->getLineItemId()]->setQuantity($quantity->getQuantity() + $itemToRemove->getQuantity());

                continue;
            }
            $lineItemsToRemove[$itemToRemove->getLineItemId()] = $itemToRemove;
        }

        /** @var array<string> $lineItemsToRemoveIDs */
        $lineItemsToRemoveIDs = array_keys($lineItemsToRemove);

        $newRestOfCart = new LineItemFlatCollection();

        // this is our running buffer
        // for the items that need to be removed
        $deleteBuffer = [];

        // make sure we have an ID index for
        // all our delete-items with a qty of 0
        foreach (array_keys($lineItemsToRemove) as $id) {
            $deleteBuffer[$id] = 0;
        }

        foreach ($restOfCart as $item) {
            // if its a totally different item
            // just add it to the rest of our cart
            if (!\in_array($item->getId(), $lineItemsToRemoveIDs, true)) {
                $newRestOfCart->add($item);
            } else {
                // we have an item that should be removed
                // now we have to calculate how many of the item position (qty diff)
                // or if we have even reached our max amount of quantities to remove for this item
                $maxRemoveMeta = $lineItemsToRemove[$item->getId()]->getQuantity();

                $alreadyDeletedCount = $deleteBuffer[$item->getId()];

                // now check if we can remove our current item completely
                // or if we have a sub quantity that still needs to be
                // added to the rest of the cart
                if ($alreadyDeletedCount + $item->getQuantity() <= $maxRemoveMeta) {
                    // remove completely
                    $deleteBuffer[$item->getId()] += $item->getQuantity();
                } else {
                    $toDeleteCount = $maxRemoveMeta - $alreadyDeletedCount;
                    $keepCount = $item->getQuantity() - $toDeleteCount;

                    // mark our diff as "deleted"
                    $deleteBuffer[$item->getId()] += $toDeleteCount;

                    // add the keep count to our item
                    // and the item to the rest of our cart
                    $item->setQuantity($keepCount);
                    $newRestOfCart->add($item);
                }
            }
        }

        return $newRestOfCart;
    }

    /**
     * @throws CartException
     */
    private function splitQuantities(LineItemCollection $cartItems, SalesChannelContext $context): LineItemFlatCollection
    {
        $items = [];

        foreach ($cartItems as $item) {
            $isStackable = $item->isStackable();

            $item->setStackable(true);

            for ($i = 1; $i <= $item->getQuantity(); ++$i) {
                $tmpItem = $this->quantitySplitter->split($item, 1, $context);

                $items[] = $tmpItem;
            }

            $item->setStackable($isStackable);
        }

        return new LineItemFlatCollection($items);
    }
}
