<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Extension;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Event\CartEvent;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @codeCoverageIgnore
 *
 * @extends Extension<list<LineItem>>
 */
#[Package('checkout')]
final class CheckoutCartAddOrderLineItemsExtension extends Extension implements ShopwareSalesChannelEvent, CartEvent
{
    public const NAME = 'checkout.cart.add-order-line-items';

    /**
     * @internal shopware owns the __constructor, but the properties are public API
     */
    public function __construct(
        /**
         * @public
         *
         * @description The order being re-added. The built line items carry no persisted payload, so this is where a
         * listener reads its own data to rebuild its line item types
         */
        public readonly OrderEntity $order,
        /**
         * @public
         *
         * @description The cart the built line items are about to be added to
         */
        public readonly Cart $cart,
        /**
         * @public
         *
         * @description Contains the current customer session parameters
         */
        public readonly SalesChannelContext $context
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
