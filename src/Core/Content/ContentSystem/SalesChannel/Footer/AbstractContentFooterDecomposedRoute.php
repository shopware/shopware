<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\SalesChannel\Footer;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * This route returns footer layout metadata with decomposed format (skeletons + data + assignments).
 */
#[Package('discovery')]
abstract class AbstractContentFooterDecomposedRoute
{
    abstract public function getDecorated(): AbstractContentFooterDecomposedRoute;

    abstract public function load(Request $request, SalesChannelContext $context): ContentFooterDecomposedRouteResponse;
}
