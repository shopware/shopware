<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cookie\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @experimental stableVersion:v6.8.0 feature:COOKIE_GROUPS_STORE_API
 */
#[Package('framework')]
abstract class AbstractCookieRoute
{
    abstract public function getDecorated(): AbstractCookieRoute;

    abstract public function getCookieGroups(Request $request, SalesChannelContext $salesChannelContext): CookieRouteResponse;
}
