<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Extension;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Event\CartEvent;
use Shopware\Core\Checkout\Cart\Order\OrderPlaceResult;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @codeCoverageIgnore
 *
 * @extends Extension<OrderPlaceResult>
 */
#[Package('checkout')]
final class CheckoutPlaceOrderExtension extends Extension implements ShopwareSalesChannelEvent, CartEvent
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

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->context;
    }

    public function getContext(): Context
    {
        return $this->context->getContext();
    }

    public function getCart(): Cart
    {
        return $this->cart;
    }
}
