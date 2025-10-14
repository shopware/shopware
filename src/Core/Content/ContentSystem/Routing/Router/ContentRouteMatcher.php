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

        /** @var ContentRouteEntity|null $contentRoute */
        $contentRoute = $parameters['_content_route'] ?? null;

        if (!$contentRoute instanceof ContentRouteEntity) {
            return null;
        }

        unset($parameters['_content_route'], $parameters['_content_route_id'], $parameters['_route']);

        return new RouteMatchResult($contentRoute, $parameters);
    }
}
