<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * This route returns layout metadata with element trees before hydration.
 */
#[Package('discovery')]
abstract class AbstractContentSkeletonRoute
{
    abstract public function getDecorated(): AbstractContentSkeletonRoute;

    abstract public function load(string $path, Request $request, SalesChannelContext $context): ContentSkeletonRouteResponse;
}
