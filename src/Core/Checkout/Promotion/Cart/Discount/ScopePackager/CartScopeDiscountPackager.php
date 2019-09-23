<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Discount\ScopePackager;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemQuantity;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceDefinitionInterface;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class CartScopeDiscountPackager
{
    /**
     * Gets all product line items of the entire cart that
     * match the rules and conditions of the provided discount item.
     */
    public function getMatchingItems(LineItem $discount, Cart $cart, SalesChannelContext $context): array
    {
        /** @var LineItemCollection $allItems */
        $allItems = $cart->getLineItems()->filterType(LineItem::PRODUCT_LINE_ITEM_TYPE);

        /** @var PriceDefinitionInterface $priceDefinition */
        $priceDefinition = $discount->getPriceDefinition();

        /** @var array $foundItems */
        $foundItems = [];

        /** @var LineItem $cartLineItem */
        foreach ($allItems as $cartLineItem) {
            if ($this->isRulesFilterValid($cartLineItem, $priceDefinition, $context)) {
                $foundItems[] = new LineItemQuantity(
                    $cartLineItem->getId(),
                    $cartLineItem->getQuantity()
                );
            }
        }

        return $foundItems;
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
}
