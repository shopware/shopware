<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * This route can be used to handle the payment for an order.
 */
#[Package('checkout')]
abstract class AbstractHandlePaymentMethodRoute
{
    abstract public function getDecorated(): AbstractHandlePaymentMethodRoute;

    abstract public function load(Request $request, SalesChannelContext $context): HandlePaymentMethodRouteResponse;
}
