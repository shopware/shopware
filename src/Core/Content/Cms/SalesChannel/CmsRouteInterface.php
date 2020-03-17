<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\SalesChannel;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * This route can be used to load a single resolved cms page of the authenticated sales channel.
 */
interface CmsRouteInterface
{
    public function load(string $id, Request $request, SalesChannelContext $context): CmsRouteResponse;
}
