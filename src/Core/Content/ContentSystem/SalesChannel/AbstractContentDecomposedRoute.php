<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * This route returns layout metadata with element skeletons, deduplicated property data, and element-to-data mappings.
 */
#[Package('discovery')]
abstract class AbstractContentDecomposedRoute
{
    abstract public function getDecorated(): AbstractContentDecomposedRoute;

    abstract public function load(string $path, Request $request, SalesChannelContext $context): ContentDecomposedRouteResponse;
}
