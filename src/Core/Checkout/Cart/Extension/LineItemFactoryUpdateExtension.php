<?php

namespace Shopware\Core\Checkout\Cart\Extension;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @title Factory extension point between request and cart logic
 * @description This extension point is used to transfer request data to line items. It is triggered when the client wants to update existing line item data inside the cart. This is used to add custom data to line items or manipulate the input before the core logic is executed. It also allows adding custom validation logic or introducing own line item types.
 */
class LineItemFactoryUpdateExtension extends Extension
{
    public function __construct(
        /**
         * @description the current customer cart. The cart is already calculated and the item is also part of it already
         */
        public Cart $cart,

        /**
         * @description The line item which is requested to be updated.
         */
        public LineItem $lineItem,

        /**
         * @public
         * @description contains the request data which was sent by the client to update this line item
         */
        public array $data,

        /**
         * @public
         * @description Reflects the current customer session
         */
        public SalesChannelContext $context
    ) {
    }

    public static function name(): string
    {
        return 'cart.line-item.factory.update';
    }

    public function result(): void
    {
    }
}
