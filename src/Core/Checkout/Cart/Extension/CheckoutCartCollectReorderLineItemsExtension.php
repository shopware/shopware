<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Extension;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @codeCoverageIgnore
 *
 * @extends Extension<list<LineItem>>
 */
#[Package('checkout')]
final class CheckoutCartCollectReorderLineItemsExtension extends Extension
{
    public const NAME = 'checkout.cart.collect-reorder-line-items';

    /**
     * @internal
     */
    public function __construct(
        /**
         * @public
         *
         * @description Allows you to access the order that is re-added. The collected line items are built from
         * scratch and carry no persisted payload, so this is where you read your own data to rebuild your line
         * item types
         */
        public readonly OrderEntity $order,

        /**
         * @public
         *
         * @description Allows you to access to the cart the collected line items are added to
         */
        public readonly Cart $cart,

        /**
         * @public
         *
         * @description Allows you to access to the current customer/sales-channel context
         */
        public readonly SalesChannelContext $context
    ) {
    }
}
