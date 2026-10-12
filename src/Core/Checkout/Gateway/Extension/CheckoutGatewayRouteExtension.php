<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Gateway\Extension;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Gateway\SalesChannel\CheckoutGatewayRouteResponse;
use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<CheckoutGatewayRouteResponse>
 */
#[Package('checkout')]
final class CheckoutGatewayRouteExtension extends Extension
{
    public const NAME = 'checkout-gateway-route.load';

    /**
     * @internal Shopware owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly Request $request,
        public readonly Cart $cart,
        public readonly SalesChannelContext $context,
    ) {
    }
}
