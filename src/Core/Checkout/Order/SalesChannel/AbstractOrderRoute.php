<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\SalesChannel;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Order\Exception\GuestNotAuthenticatedException;
use Shopware\Core\Checkout\Order\Exception\WrongGuestCredentialsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * This route is used to load the orders of the logged-in customer
 * With this route it is also possible to send the standard API parameters such as: 'page', 'limit', 'filter', etc.
 */
#[Package('customer-order')]
abstract class AbstractOrderRoute
{
    abstract public function getDecorated(): AbstractOrderRoute;

    /**
     * @throws CustomerNotLoggedInException
     * @throws GuestNotAuthenticatedException
     * @throws WrongGuestCredentialsException
     */
    abstract public function load(Request $request, SalesChannelContext $context, Criteria $criteria): OrderRouteResponse;
}
