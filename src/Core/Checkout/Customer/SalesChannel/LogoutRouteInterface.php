<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\System\SalesChannel\NoContentResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * This route can be used to logout the current context token
 */
interface LogoutRouteInterface
{
    public function logout(SalesChannelContext $context): NoContentResponse;
}
