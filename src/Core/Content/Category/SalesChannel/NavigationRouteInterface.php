<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\SalesChannel;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

interface NavigationRouteInterface
{
    public function load(
        string $requestActiveId,
        string $requestRootId,
        int $depth = 2,
        Request $request,
        SalesChannelContext $context
    ): NavigationRouteResponse;
}
