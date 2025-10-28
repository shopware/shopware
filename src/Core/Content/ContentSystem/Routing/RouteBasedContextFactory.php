<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Routing;

use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Content\ContentSystem\Helper\RequestParameterExtractor;
use Shopware\Core\Content\ContentSystem\PlaceholderValues;
use Shopware\Core\Content\ContentSystem\RenderingSpecification;
use Shopware\Core\Content\ContentSystem\RenderingSpecificationFactoryInterface;
use Shopware\Core\Content\ContentSystem\Routing\IdResolution\EntityIdResolver;
use Shopware\Core\Content\ContentSystem\Routing\LayoutResolution\LayoutResolver;
use Shopware\Core\Content\ContentSystem\Routing\Router\ContentRouter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
class RouteBasedContextFactory implements RenderingSpecificationFactoryInterface
{
    public function __construct(
        private readonly ContentRouter $contentRouter,
        private readonly EntityIdResolver $entityIdResolver,
        private readonly LayoutResolver $layoutResolver,
        private readonly RequestParameterExtractor $requestParameterExtractor
    ) {
    }

    public function create(string $path, Request $request, SalesChannelContext $context): ?RenderingSpecification
    {
        $pathInfo = '/' . ltrim($path, '/');
        $match = $this->contentRouter->match($pathInfo, $context);

        if ($match === null) {
            throw ContentSystemException::contentNotFound($pathInfo);
        }

        $route = $match->route;

        $resolvedData = $this->entityIdResolver->resolve($match, $context);

        $layoutId = $this->layoutResolver->resolve($match->route, $resolvedData, $context);

        if ($layoutId === null) {
            $resolvedPlaceholders = array_keys($resolvedData->entityIds->toArray());

            throw ContentSystemException::layoutAssignmentNotFoundForRoute(
                $route->getId(),
                $route->getUrlPattern(),
                $resolvedPlaceholders,
                $context->getSalesChannel()->getId()
            );
        }

        $placeholderValues = PlaceholderValues::from($resolvedData->getValues());

        $targetElementId = $this->requestParameterExtractor->extractTargetElementId($request);

        return new RenderingSpecification(
            layoutId: $layoutId,
            placeholderValues: $placeholderValues,
            targetElementId: $targetElementId
        );
    }
}
