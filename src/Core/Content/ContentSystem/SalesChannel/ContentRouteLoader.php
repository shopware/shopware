<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\SalesChannel;

use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Content\ContentSystem\Hydration\ContentElementHydrator;
use Shopware\Core\Content\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Content\ContentSystem\Layout\Refinery\RefinedLayoutBuilder;
use Shopware\Core\Content\ContentSystem\Output\RenderingContext;
use Shopware\Core\Content\ContentSystem\Output\Struct\ContentPage;
use Shopware\Core\Content\ContentSystem\Output\SubTreeExtractor;
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
class ContentRouteLoader
{
    public function __construct(
        private readonly ContentRouter $contentRouter,
        private readonly EntityIdResolver $entityIdResolver,
        private readonly LayoutResolver $layoutResolver,
        private readonly RefinedLayoutBuilder $refinedLayoutBuilder,
        private readonly ContentElementHydrator $hydrationService,
        private readonly SubTreeExtractor $subTreeExtractor
    ) {
    }

    /**
     * Orchestrates the content system pipeline to load and render content.
     *
     * The pipeline consists of five phases that must run sequentially. After hydration,
     * partial rendering may extract a subtree if ?elementId parameter is present.
     *
     * @param string $path URL path to match (e.g., "/product/laptop-x1")
     * @param Request $request HTTP request containing optional query parameters (?elementId)
     * @param SalesChannelContext $context Sales channel context for route/layout filtering
     *
     * @throws ContentSystemException When route not found, entity resolution fails,
     *                                layout assignment not found, refinement fails,
     *                                or requested element ID not found in tree
     *
     * @return ContentPage Fully hydrated content page with element tree and metadata
     */
    public function load(string $path, Request $request, SalesChannelContext $context): ContentPage
    {
        // Normalize path to match stored URL patterns. Routes are persisted with leading slash
        // and no trailing slash. This normalization ensures consistent matching regardless of
        // how the client formats the request path.
        $pathInfo = '/' . ltrim($path, '/');

        $match = $this->contentRouter->match($pathInfo, $context);

        if ($match === null) {
            throw ContentSystemException::contentNotFound($pathInfo);
        }

        $route = $match->route;

        $resolvedData = $this->entityIdResolver->resolve($match, $context);

        $layoutId = $this->layoutResolver->resolve($match->route, $resolvedData, $context);

        if ($layoutId === null) {
            // LayoutResolver returns null when no assignment matches the route context.
            // This can occur when: entity resolution succeeded but no layout assignments
            // match the resolved entity types/IDs, or route has no assignments for this
            // sales channel. Provide resolved placeholders in error for debugging.
            $resolvedPlaceholders = array_keys($resolvedData->entityIds->toArray());

            throw ContentSystemException::layoutAssignmentNotFoundForRoute(
                $route->getId(),
                $route->getUrlPattern(),
                $resolvedPlaceholders,
                $context->getSalesChannel()->getId()
            );
        }

        $renderingContext = RenderingContext::fromRequest($request);

        // Wrap refinement exceptions with context for debugging. The refinement phase can fail
        // due to: layout entity not found in DB, refiner execution errors, or placeholder
        // resolution issues. Wrapping preserves the original exception as cause while clearly
        // indicating which pipeline phase failed (refinement vs routing, hydration, etc.).
        try {
            $refinedLayout = $this->refinedLayoutBuilder->build($layoutId, $resolvedData, $renderingContext, $context);
        } catch (\Throwable $e) {
            throw ContentSystemException::layoutRefineryFailed($layoutId, $e->getMessage(), $e);
        }

        $this->hydrationService->hydrate($refinedLayout, $context);

        $rootElement = $this->applyPartialRendering(
            $refinedLayout->rootElement,
            $renderingContext
        );

        return new ContentPage(
            layoutId: $layoutId,
            layout: $rootElement,
            layoutName: $refinedLayout->layoutEntity->getName(),
            layoutVersion: $refinedLayout->layoutEntity->getVersionId(),
            route: $route
        );
    }

    private function applyPartialRendering(
        ContentElement $rootElement,
        RenderingContext $renderingContext
    ): ContentElement {
        $targetElementId = $renderingContext->targetElementId;
        // Treat empty string as null - query parameters can be empty when ?elementId is
        // present without a value. Both cases mean "no partial rendering requested".
        if ($targetElementId === null || $targetElementId === '') {
            return $rootElement;
        }

        $targetElement = $this->subTreeExtractor->extract($rootElement, $targetElementId);

        if ($targetElement === null) {
            throw ContentSystemException::elementNotFound($targetElementId);
        }

        return $targetElement;
    }
}
