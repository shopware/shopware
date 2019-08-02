<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\LineItem\Group;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItemFlatCollection;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class LineItemGroupBuilder
{
    /**
     * @var LineItemGroupServiceRegistry
     */
    private $registry;

    /**
     * @var LineItemGroupRuleMatcherInterface
     */
    private $ruleMatcher;

    /**
     * @var QuantityPriceCalculator
     */
    private $quantityPriceCalculator;

    public function __construct(LineItemGroupServiceRegistry $registry, LineItemGroupRuleMatcherInterface $ruleMatcher, QuantityPriceCalculator $quantityPriceCalculator)
    {
        $this->registry = $registry;
        $this->ruleMatcher = $ruleMatcher;
        $this->quantityPriceCalculator = $quantityPriceCalculator;
    }

    /**
     * Searches for all packages that can be built from the provided list of groups.
     * Every line item will be taken from the cart and only the ones that are left will
     * be checked for upcoming groups.
     *
     * @throws Exception\LineItemGroupPackagerNotFoundException
     * @throws Exception\LineItemGroupSorterNotFoundException
     * @throws \Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException
     * @throws \Shopware\Core\Checkout\Cart\Exception\LineItemNotStackableException
     * @throws \Shopware\Core\Checkout\Cart\Exception\MixedLineItemTypeException
     */
    public function findGroupPackages(array $groupDefinitions, Cart $cart, SalesChannelContext $context): LineItemGroupBuilderResult
    {
        $result = new LineItemGroupBuilderResult();

        // filter out all promotion items
        $cartProducts = $this->getCartProducts($cart);

        // split quantities into separate line items
        // so we have a real list of products like we would have
        // them when holding it in our actual hands.
        /** @var LineItemFlatCollection $restOfCart */
        $restOfCart = $this->splitQuantities($cartProducts, $context);

        /** @var LineItemGroupDefinition $groupDefinition */
        foreach ($groupDefinitions as $groupDefinition) {
            /** @var LineItemGroupSorterInterface $sorter */
            $sorter = $this->registry->getSorter($groupDefinition->getSorterKey());

            /** @var LineItemGroupPackagerInterface $packager */
            $packager = $this->registry->getPackager($groupDefinition->getPackagerKey());

            // try as long as groups can be
            // found for the current definition
            while (true) {
                /** @var LineItemFlatCollection $itemsToConsider */
                $itemsToConsider = $this->ruleMatcher->getMatchingItems($groupDefinition, $restOfCart, $context);

                // sort using our found sorter
                $itemsToConsider = $sorter->sort($itemsToConsider);

                // now build a package with our packager
                /** @var LineItemGroup $group */
                $group = $packager->buildGroupPackage($groupDefinition->getValue(), $itemsToConsider, $context);

                // if we have no found items in our group, quit
                if (!$group->hasItems()) {
                    break;
                }

                // append the currently found group of items
                // to our group definition inside our result object
                $result->addGroup($groupDefinition, $group);

                // decrease rest of cart items for next search
                /** @var LineItemFlatCollection $restOfCart */
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
     */
    private function adjustRestOfCart(array $foundItems, LineItemFlatCollection $restOfCart): LineItemFlatCollection
    {
        // a holder for all foundItems indexed by lineItemId
        $removeLineItemIds = [];

        // we prepare the removeLineItemIds array with all LineItemQuantity objects indexed by lineItemId
        /* @var LineItemQuantity $itemToRemove */
        foreach (array_values($foundItems) as $itemToRemove) {
            if (isset($removeLineItemIds[$itemToRemove->getLineItemId()])) {
                /** @var LineItemQuantity $qty */
                $qty = $removeLineItemIds[$itemToRemove->getLineItemId()];
                $removeLineItemIds[$itemToRemove->getLineItemId()]->setQuantity($qty->getQuantity() + $itemToRemove->getQuantity());
                continue;
            }
            $removeLineItemIds[$itemToRemove->getLineItemId()] = $itemToRemove;
        }

        // filter the LineItemFlatCollection by all lineItemIds that are not present in removeLineItemIds,
        // because we want them in our rest cart in any case
        $filteredCartLineItems = $restOfCart->filter(function (LineItem $addToRest) use ($removeLineItemIds) {
            return !isset($removeLineItemIds[$addToRest->getId()]);
        });

        // now we iterate over our prepared $removeLineItemIds array
        // here we are skipping lineItems that have a lineItemId that should be deleted
        // but we are doing this only in case the quantity is lower
        // if higher we add, because we want to keep these items
        /*
         * @var LineItemQuantity
         */
        foreach ($removeLineItemIds as $lineItemId => $lineItemQuantity) {
            $removeQuantity = $lineItemQuantity->getQuantity();

            // get all collection lineItems which have the same lineItemId as our lineItemQuantity object
            $foundItemsCollection = $restOfCart->filter(function (LineItem $addToRest) use ($lineItemId) {
                return $addToRest->getId() === $lineItemId;
            });

            $foundItemsArray = $foundItemsCollection->getElements();

            $foundItemsQuantity = count($foundItemsArray);

            // all items that are above the defined quantity have to be added to our result collection
            for ($i = $foundItemsQuantity; $i > $removeQuantity; --$i) {
                $filteredCartLineItems->add($foundItemsArray[$i - 1]);
            }
        }

        return $filteredCartLineItems;
    }

    private function getCartProducts(Cart $cart): LineItemCollection
    {
        return $cart->getLineItems()->filterType(LineItem::PRODUCT_LINE_ITEM_TYPE);
    }

    /**
     * @throws \Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException
     * @throws \Shopware\Core\Checkout\Cart\Exception\LineItemNotStackableException
     */
    private function splitQuantities(LineItemCollection $cartItems, SalesChannelContext $context): LineItemFlatCollection
    {
        $items = [];

        /** @var LineItem $item */
        foreach ($cartItems as $item) {
            for ($i = 1; $i <= $item->getQuantity(); ++$i) {
                // clone the original line item
                $tmpItem = LineItem::createFromLineItem($item);

                // use calculated unit price
                /** @var float $unitPrice */
                $unitPrice = $tmpItem->getPrice()->getUnitPrice();

                /** @var TaxRuleCollection $taxRules */
                $taxRules = $tmpItem->getPrice()->getTaxRules();

                // change the quantity to 1 single item
                $tmpItem->setQuantity(1);

                /** @var QuantityPriceDefinition $quantityDefinition */
                $quantityDefinition = new QuantityPriceDefinition(
                    $unitPrice,
                    $taxRules,
                    $context->getContext()->getCurrencyPrecision(),
                    $tmpItem->getQuantity(),
                    true
                );

                /** @var CalculatedPrice $price */
                $price = $this->quantityPriceCalculator->calculate(
                    $quantityDefinition,
                    $context
                );
                $tmpItem->setPrice($price);

                $items[] = $tmpItem;
            }
        }

        return new LineItemFlatCollection($items);
    }
}
