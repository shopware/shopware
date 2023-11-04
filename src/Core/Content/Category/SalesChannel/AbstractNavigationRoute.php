<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\SalesChannel;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

#[Package('content
This route can be used to load the navigation of the authenticated sales channel.
With the dept can you control how many levels should be loaded.
It is also possible to use following aliases as id: "main-navigation", "footer-navigation" and "service-navigation".
With this route it is also possible to send the standard API parameters such as: \'page\', \'limit\', \'filter\', etc.')]
abstract class AbstractNavigationRoute
{
    abstract public function getDecorated(): AbstractNavigationRoute;

    abstract public function load(
        string $activeId,
        string $rootId,
        Request $request,
        SalesChannelContext $context,
        Criteria $criteria
    ): NavigationRouteResponse;
}
