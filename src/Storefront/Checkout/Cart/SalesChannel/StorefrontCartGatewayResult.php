<?php declare(strict_types=1);

namespace Shopware\Storefront\Checkout\Cart\SalesChannel;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Gateway\SalesChannel\CheckoutGatewayRouteResponse;
use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 *
 * @internal
 */
#[Package('checkout')]
class StorefrontCartGatewayResult
{
    public function __construct(
        public readonly Cart $cart,
        public readonly CheckoutGatewayRouteResponse $gatewayResponse,
    ) {
    }
}
