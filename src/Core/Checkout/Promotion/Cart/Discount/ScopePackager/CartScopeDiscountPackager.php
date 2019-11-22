<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Discount\ScopePackager;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemQuantity;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemQuantityCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItemFlatCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItemQuantitySplitter;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceDefinitionInterface;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountLineItem;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackage;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackageCollection;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackagerInterface;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class CartScopeDiscountPackager
{
    /**
     * @var LineItemQuantitySplitter
     */
    private $lineItemQuantitySplitter;

    public function __construct(LineItemQuantitySplitter $lineItemQuantitySplitter)
    {
        $this->lineItemQuantitySplitter = $lineItemQuantitySplitter;
    }

    public function getResultContext(): string
    {
        return DiscountPackagerInterface::RESULT_CONTEXT_LINEITEM;
    }

    /**
     * Gets all product line items of the entire cart that
     * match the rules and conditions of the provided discount item.
     */
    public function getMatchingItems(DiscountLineItem $discount, Cart $cart, SalesChannelContext $context): DiscountPackageCollection
    {
        $allItems = $cart->getLineItems()->filterType(LineItem::PRODUCT_LINE_ITEM_TYPE);

        $singleItems = $this->splitQuantities($allItems, $context);

        $priceDefinition = $discount->getPriceDefinition();

        /** @var array $foundItems */
        $foundItems = [];

        foreach ($singleItems as $cartLineItem) {
            if ($this->isRulesFilterValid($cartLineItem, $priceDefinition, $context)) {
                $item = new LineItemQuantity(
                    $cartLineItem->getId(),
                    $cartLineItem->getQuantity()
                );

                $foundItems[] = $item;
            }
        }

        $package = new DiscountPackage(new LineItemQuantityCollection($foundItems));

        return new DiscountPackageCollection([$package]);
    }

    private function isRulesFilterValid(LineItem $item, PriceDefinitionInterface $priceDefinition, SalesChannelContext $context): bool
    {
        // if the price definition doesnt allow filters,
        // then return valid for the item
        if (!method_exists($priceDefinition, 'getFilter')) {
            return true;
        }

        /** @var Rule|null $filter */
        $filter = $priceDefinition->getFilter();

        // if the definition exists, but is empty
        // this means we have no restrictions and thus its valid
        if (!$filter instanceof Rule) {
            return true;
        }

        // if our price definition has a filter rule
        // then extract it, and check if it matches
        $scope = new LineItemScope($item, $context);

        if ($filter->match($scope)) {
            return true;
        }

        return false;
    }

    /**
     * @throws \Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException
     * @throws \Shopware\Core\Checkout\Cart\Exception\LineItemNotStackableException
     */
    private function splitQuantities(LineItemCollection $cartItems, SalesChannelContext $context): LineItemFlatCollection
    {
        $items = [];

        foreach ($cartItems as $item) {
            for ($i = 1; $i <= $item->getQuantity(); ++$i) {
                $tmpItem = $this->lineItemQuantitySplitter->split($item, 1, $context);

                $items[] = $tmpItem;
            }
        }

        return new LineItemFlatCollection($items);
    }
}
