<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Routing\Router;

use Shopware\Core\Content\ContentSystem\Routing\Entity\ContentRouteEntity;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;

/**
 * @final
 */
#[Package('discovery')]
class ContentRouteMatcher
{
    public function match(string $pathInfo, RouteCollection $routes): ?RouteMatchResult
    {
        $context = new RequestContext();
        $context->setPathInfo($pathInfo);

        $matcher = new UrlMatcher($routes, $context);

        try {
            $parameters = $matcher->match($pathInfo);
        } catch (ResourceNotFoundException) {
            return null;
        }

        $contentRoute = $parameters[RouteCollectionBuilder::PARAM_CONTENT_ROUTE] ?? null;

        if (!$contentRoute instanceof ContentRouteEntity) {
            return null;
        }

        unset(
            $parameters[RouteCollectionBuilder::PARAM_CONTENT_ROUTE],
            $parameters[RouteCollectionBuilder::PARAM_CONTENT_ROUTE_ID],
            $parameters[RouteCollectionBuilder::PARAM_ROUTE]
        );

        return new RouteMatchResult($contentRoute, $parameters);
    }
}
