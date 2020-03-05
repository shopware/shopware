<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\SalesChannel;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

interface CategoryRouteInterface
{
    public function load(string $navigationId, Request $request, SalesChannelContext $context): CategoryRouteResponse;
}
