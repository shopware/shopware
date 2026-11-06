<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Extension;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @public this class is used as type-hint for all event listeners, so the class string is "public consumable" API
 *
 * @title Determination of the shipping methods loaded for the cart deliveries
 *
 * @description This event allows adding shipping methods to the ones the delivery processor loads, so a delivery can carry a method that neither the context nor a delivery of the previous calculation had
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<ShippingMethodCollection>
 */
#[Package('checkout')]
final class CollectDeliveryShippingMethodsExtension extends Extension
{
    public const NAME = 'delivery-processor.collect-shipping-methods';

    /**
     * @internal shopware owns the __constructor, but the properties are public API
     */
    public function __construct(
        /**
         * @public
         *
         * @description The ids of the shipping methods to load. Add the ids your deliveries need; ids that are already loaded for this cart are skipped
         *
         * @var list<string>
         */
        public array $shippingMethodIds,
        /**
         * @public
         *
         * @description Allows you to access the cart of the previous calculation, including its deliveries and extensions
         */
        public readonly Cart $cart,
        /**
         * @public
         *
         * @description Allows you to access the current customer/sales-channel context
         */
        public readonly SalesChannelContext $context,
        /**
         * @public
         *
         * @description Allows you to access the behavior of the current cart calculation
         */
        public readonly CartBehavior $behavior,
    ) {
    }
}
