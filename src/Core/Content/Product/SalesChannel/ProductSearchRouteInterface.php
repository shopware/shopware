<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

interface ProductSearchRouteInterface
{
    public function load(Request $request, SalesChannelContext $context): ProductSearchRouteResponse;
}
