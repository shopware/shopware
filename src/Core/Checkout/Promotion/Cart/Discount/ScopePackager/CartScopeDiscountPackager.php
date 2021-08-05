<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Discount\ScopePackager;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException;
use Shopware\Core\Checkout\Cart\Exception\LineItemNotStackableException;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemQuantity;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemQuantityCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItemFlatCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItemQuantitySplitter;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountLineItem;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackage;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackageCollection;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackager;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class CartScopeDiscountPackager extends DiscountPackager
{
    /**
     * @var LineItemQuantitySplitter
     */
    private $lineItemQuantitySplitter;

    public function __construct(LineItemQuantitySplitter $lineItemQuantitySplitter)
    {
        $this->lineItemQuantitySplitter = $lineItemQuantitySplitter;
    }

    public function getDecorated(): DiscountPackager
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * Gets all product line items of the entire cart that
     * match the rules and conditions of the provided discount item.
     */
    public function getMatchingItems(DiscountLineItem $discount, Cart $cart, SalesChannelContext $context): DiscountPackageCollection
    {
        $allItems = $cart->getLineItems()->filter(function (LineItem $lineItem) {
            return $lineItem->getType() === LineItem::PRODUCT_LINE_ITEM_TYPE && $lineItem->isStackable();
        });

        $singleItems = $this->splitQuantities($allItems, $context);

        $foundItems = [];

        foreach ($singleItems as $cartLineItem) {
            $item = new LineItemQuantity(
                $cartLineItem->getId(),
                $cartLineItem->getQuantity()
            );

            $foundItems[] = $item;
        }

        if ($foundItems === []) {
            return new DiscountPackageCollection([]);
        }

        $package = new DiscountPackage(new LineItemQuantityCollection($foundItems));

        return new DiscountPackageCollection([$package]);
    }

    /**
     * @throws InvalidQuantityException
     * @throws LineItemNotStackableException
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
