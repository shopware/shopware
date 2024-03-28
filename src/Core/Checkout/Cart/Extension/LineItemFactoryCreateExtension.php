<?php

namespace Shopware\Core\Checkout\Cart\Extension;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @title Factory extension point to transfer request data to line items
 * @description This extension point is used to transfer request data to line items. This is used to add custom data to line items or manipulate the input before the core logic is executed. It also allows adding custom validation logic or introducing own line item types.
 */
class LineItemFactoryCreateExtension extends Extension
{
    public function __construct(
        /**
         * @public
         * @description contains the request data which was sent by the client to add this line item to the cart
         */
        public array $data,

        /**
         * @public
         * @description
         */
        public SalesChannelContext $context
    ) {
    }

    public static function name(): string
    {
        return 'cart.line-item.factory.create';
    }

    public function result(): LineItem
    {
        return $this->result;
    }
}
