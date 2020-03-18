<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * This route is used to get information about the current logged-in customer
 */
interface CustomerRouteInterface
{
    public function load(Request $request, SalesChannelContext $context): CustomerRouteResponse;
}
