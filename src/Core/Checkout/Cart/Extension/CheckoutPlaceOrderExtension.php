<?php

namespace Shopware\Core\Checkout\Cart\Extension;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Order\OrderPlaceResult;
use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @experimental stableVersion:v6.7.0 feature:EXTENSION_SYSTEM
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<OrderPlaceResult>
 */
class CheckoutPlaceOrderExtension extends Extension
{
    public const NAME = 'checkout.place-order';

    /**
     * @internal shopware owns the __constructor, but the properties are public API
     */
    public function __construct(
        /**
         * @public
         *
         * @description The cart is already calculated and can be processed to place the order
         */
        public readonly Cart $cart,
        /**
         * @public
         *
         * @description Contains the current customer session parameters
         */
        public readonly SalesChannelContext $context,
        /**
         * @public
         *
         * @description Contains additional request parameters like customer comments etc.
         */
        public readonly RequestDataBag $data
    ) {
    }
}
