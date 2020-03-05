<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\SalesChannel;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

interface CmsRouteInterface
{
    public function load(string $id, Request $request, SalesChannelContext $context): CmsRouteResponse;
}
