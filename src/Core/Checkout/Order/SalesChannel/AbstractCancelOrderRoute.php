<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\SalesChannel;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * This route is used to cancel a order
 */
abstract class AbstractCancelOrderRoute
{
    abstract public function getDecorated(): AbstractCancelOrderRoute;

    abstract public function cancel(Request $request, SalesChannelContext $context): CancelOrderRouteResponse;
}
