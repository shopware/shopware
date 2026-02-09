<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\SalesChannel\Header;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * This route returns header layout metadata with decomposed format (skeletons + data + assignments).
 */
#[Package('discovery')]
abstract class AbstractContentHeaderDecomposedRoute
{
    abstract public function getDecorated(): AbstractContentHeaderDecomposedRoute;

    abstract public function load(Request $request, SalesChannelContext $context): ContentHeaderDecomposedRouteResponse;
}
