<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Extension;

use Shopware\Core\Checkout\Payment\SalesChannel\HandlePaymentMethodRouteResponse;
use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<HandlePaymentMethodRouteResponse>
 */
#[Package('checkout')]
final class HandlePaymentMethodRouteExtension extends Extension
{
    public const NAME = 'handle-payment-method-route.load';

    /**
     * @internal Shopware owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly Request $request,
        public readonly SalesChannelContext $context,
    ) {
    }
}
