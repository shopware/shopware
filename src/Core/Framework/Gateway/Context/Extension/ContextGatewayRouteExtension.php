<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Gateway\Context\Extension;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\ContextTokenResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<ContextTokenResponse>
 */
#[Package('framework')]
final class ContextGatewayRouteExtension extends Extension
{
    public const NAME = 'context-gateway-route.load';

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
