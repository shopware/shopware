<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cookie\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * This route is used to get the hash representing all cookie groups and their entries.
 */
#[Package('framework')]
abstract class AbstractCookieHashRoute
{
    abstract public function getDecorated(): AbstractCookieHashRoute;

    abstract public function getCookieHash(Request $request, SalesChannelContext $salesChannelContext): CookieHashRouteResponse;
}
