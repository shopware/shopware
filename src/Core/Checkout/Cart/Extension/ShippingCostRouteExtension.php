<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Extension;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\SalesChannel\ShippingCostRouteResponse;
use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<ShippingCostRouteResponse>
 */
#[Package('checkout')]
final class ShippingCostRouteExtension extends Extension
{
    public const NAME = 'shipping-cost-route.shipping-costs-cart';

    /**
     * @internal Shopware owns the constructor; the properties are public API.
     *
     * @param non-empty-list<string>|null $availableShippingMethodIds
     */
    public function __construct(
        public readonly Cart $cart,
        public readonly SalesChannelContext $salesChannelContext,
        public readonly ?array $availableShippingMethodIds,
    ) {
    }
}
