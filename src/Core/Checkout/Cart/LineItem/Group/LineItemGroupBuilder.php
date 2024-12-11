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
     * @var array<string, LineItemGroupBuilderResult|null>
     */
    private array $results = [];

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

        foreach ($groupDefinitions as $index => $groupDefinition) {
            if (!\array_key_exists($groupDefinition->getId(), $this->results) || $this->results[$groupDefinition->getId()] === null) {
                continue;
            }

            $result->addGroupResult($groupDefinition->getId(), $this->results[$groupDefinition->getId()]);

            unset($groupDefinitions[$index]);
        }

        if (empty($groupDefinitions)) {
            return $result;
        }

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

            // get all items that match the current group definition
            $itemsToConsider = $this->ruleMatcher->getMatchingItems($groupDefinition, $restOfCart, $context);

            if ($itemsToConsider->count() <= 0) {
                continue;
            }

            // try as long as groups can be
            // found for the current definition
            while (true) {
                // now build a package with our packager
                $group = $packager->buildGroupPackage($groupDefinition->getValue(), $itemsToConsider, $context);

                // if we have no found items in our group, quit
                if (!$group->hasItems()) {
                    break;
                }

                // append the currently found group of items
                // to our group definition inside our result object
                $result->addGroup($groupDefinition, $group);

                $itemsToConsider = $this->adjustRestOfCart($group->getItems(), $itemsToConsider);

                if ($itemsToConsider->count() <= 0) {
                    break;
                }
            }

            $this->results[$groupDefinition->getId()] = $result->getResult($groupDefinition->getId());
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
        $lineItemsToRemove = [];

        // we prepare the removeLineItemIds array with all LineItemQuantity objects indexed by lineItemId
        foreach ($foundItems as $itemToRemove) {
            $lineItemsToRemove[$itemToRemove->getLineItemId()] = $itemToRemove->getQuantity();
        }

        // Initialize deleteBuffer with keys from lineItemsToRemove and values set to 0
        $deleteBuffer = array_fill_keys(array_keys($lineItemsToRemove), 0);

        foreach ($restOfCart as $index => $item) {
            // If the item is not in lineItemsToRemove, keep it in the rest of our cart.
            if (!isset($lineItemsToRemove[$item->getId()])) {
                continue;
            }

            // we have an item that should be removed
            // now we have to calculate how many of the item position (qty diff)
            // or if we have even reached our max amount of quantities to remove for this item
            $maxRemoveMeta = $lineItemsToRemove[$item->getId()];
            $alreadyDeletedCount = $deleteBuffer[$item->getId()];

            // If we have reached our max amount of quantities to remove for this item, keep it in the cart.
            if ($alreadyDeletedCount === $maxRemoveMeta) {
                unset($lineItemsToRemove[$item->getId()]);

                // If we have removed all items we wanted to remove, break the loop.
                if (empty($lineItemsToRemove)) {
                    break;
                }

                continue;
            }

            // If we have not reached our max amount of quantities to remove for this item.
            if ($alreadyDeletedCount + $item->getQuantity() <= $maxRemoveMeta) {
                $deleteBuffer[$item->getId()] += $item->getQuantity();
            }

            $restOfCart->remove($index);
        }

        return $restOfCart;
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

            $tmpItem = $this->quantitySplitter->split($item, 1, $context);

            for ($i = 1; $i <= $item->getQuantity(); ++$i) {
                $items[] = $tmpItem;
            }

            $item->setStackable($isStackable);
        }

        return new LineItemFlatCollection($items);
    }
}
