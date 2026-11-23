<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Extension;

use Shopware\Core\Checkout\Order\SalesChannel\SetPaymentOrderRouteResponse;
use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<SetPaymentOrderRouteResponse>
 */
#[Package('checkout')]
final class SetPaymentOrderRouteExtension extends Extension
{
    public const NAME = 'set-payment-order-route.set-payment';

    /**
     * @internal Shopware owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly Request $request,
        public readonly SalesChannelContext $context,
    ) {
    }
}
