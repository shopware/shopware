<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\SalesChannel\Header;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * This route returns header layout metadata with element trees before hydration.
 */
#[Package('discovery')]
abstract class AbstractContentHeaderSkeletonRoute
{
    abstract public function getDecorated(): AbstractContentHeaderSkeletonRoute;

    abstract public function load(Request $request, SalesChannelContext $context): ContentHeaderSkeletonRouteResponse;
}
