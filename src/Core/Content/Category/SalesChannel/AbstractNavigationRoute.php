<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\SalesChannel;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

#[Package('content')]
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
