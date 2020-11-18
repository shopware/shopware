<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\SalesChannel;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * This route can be used to load the cart
 */
abstract class AbstractCartLoadRoute
{
    abstract public function getDecorated(): AbstractCartLoadRoute;

    abstract public function load(Request $request, SalesChannelContext $context): CartResponse;
}
