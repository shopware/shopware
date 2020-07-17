<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\SalesChannel;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * This route is used to update the paymentMethod for an order
 */
abstract class AbstractSetPaymentOrderRoute
{
    abstract public function getDecorated(): AbstractSetPaymentOrderRoute;

    abstract public function setPayment(Request $request, SalesChannelContext $context): SetPaymentOrderRouteResponse;
}
