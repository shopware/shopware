<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\SalesChannel;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractSitemapRoute
{
    abstract public function load(Request $request, SalesChannelContext $context): SitemapRouteResponse;

    abstract public function getDecorated(): AbstractSitemapRoute;
}
