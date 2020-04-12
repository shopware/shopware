<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\SalesChannel;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * This route is used to change the state of an order
 */
abstract class AbstractOrderStateChangeRoute
{
    abstract public function getDecorated(): AbstractOrderStateChangeRoute;

    abstract public function change(Request $request, SalesChannelContext $context): OrderStateChangeRouteResponse;
}
