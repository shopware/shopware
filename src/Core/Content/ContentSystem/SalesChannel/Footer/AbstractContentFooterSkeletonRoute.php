<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\SalesChannel\Footer;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * This route returns footer layout metadata with element trees before hydration.
 */
#[Package('discovery')]
abstract class AbstractContentFooterSkeletonRoute
{
    abstract public function getDecorated(): AbstractContentFooterSkeletonRoute;

    abstract public function load(Request $request, SalesChannelContext $context): ContentFooterSkeletonRouteResponse;
}
