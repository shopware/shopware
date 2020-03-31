<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\SalesChannel;

use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * This route is used to cancel an order
 * With this route it is also possible to send the standard API parameters such as: 'page', 'limit', 'filter', etc.
 */
abstract class AbstractAccountCancelOrderRoute
{
    abstract public function getDecorated(): AbstractAccountCancelOrderRoute;

    abstract public function load(Request $request, SalesChannelContext $context): AccountCancelOrderRouteResponse;
}
