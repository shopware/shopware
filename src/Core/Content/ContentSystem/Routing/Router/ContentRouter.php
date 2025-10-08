<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Routing\Router;

use Shopware\Core\Content\ContentSystem\Routing\Struct\RouteMatchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @final
 */
#[Package('discovery')]
class ContentRouter
{
    /**
     * @internal
     */
    public function __construct(
        protected readonly RouteCollectionBuilder $routeCollectionBuilder,
        protected readonly ContentRouteMatcher $matcher
    ) {
    }

    public function match(string $pathInfo, SalesChannelContext $context): ?RouteMatchResult
    {
        $routes = $this->routeCollectionBuilder->build($context);

        return $this->matcher->match($pathInfo, $routes);
    }
}
