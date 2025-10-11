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
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @final
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
#[Package('discovery')]
class ContentRoute extends AbstractContentRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ContentRouter $contentRouter,
        private readonly EntityIdResolver $entityIdResolver,
        private readonly LayoutResolver $layoutResolver,
        private readonly RefinedLayoutBuilder $refinedLayoutBuilder,
        private readonly ContentElementHydrator $hydrationService,
        private readonly SubTreeExtractor $subTreeExtractor
    ) {
    }

    public function getDecorated(): AbstractContentRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/content/{path}',
        name: 'store-api.content.detail',
        requirements: ['path' => '.+'],
        defaults: [
            '_httpCache' => true,
            'excludes' => [
                'content_element' => [
                    'dataRequirements',
                    'properties',
                    'contextDefinitions',
                ],
            ],
        ],
        methods: ['GET', 'POST']
    )]
    public function load(string $path, Request $request, SalesChannelContext $context): ContentRouteResponse
    {
        $pathInfo = '/' . ltrim($path, '/');

        $match = $this->contentRouter->match($pathInfo, $context);

        if ($match === null) {
            throw ContentSystemException::contentNotFound($pathInfo);
        }

        $route = $match->getRoute();

        try {
            $resolvedData = $this->entityIdResolver->resolve($match, $context);
        } catch (\Throwable $e) {
            throw ContentSystemException::resolutionFailed($route->getName(), $e->getMessage(), $e);
        }

        if ($resolvedData === null) {
            $parameterBinding = $route->getParameterBinding();
            $parameters = $match->getParameters();

            $firstParam = array_key_first($parameterBinding);
            if ($firstParam !== null) {
                $paramConfig = $parameterBinding[$firstParam];
                $entityType = $paramConfig['resolution']['entity'] ?? 'entity';
                $matchField = $paramConfig['resolution']['match_field'] ?? 'id';
                $value = $parameters[$firstParam] ?? 'unknown';

                throw ContentSystemException::entityNotFound($entityType, $value, $matchField);
            }

            throw ContentSystemException::entityNotFound('entity', $pathInfo, 'path');
        }

        try {
            $layoutId = $this->layoutResolver->resolve($match, $resolvedData, $context);
        } catch (\Throwable $e) {
            throw ContentSystemException::resolutionFailed($route->getName(), $e->getMessage(), $e);
        }

        if ($layoutId === null) {
            $entityIds = $resolvedData->getEntityIds();
            $entityIdsArray = $entityIds->toArray();
            $firstEntityKey = array_key_first($entityIdsArray);
            $entityType = $firstEntityKey !== null ? str_replace('_id', '', $firstEntityKey) : 'entity';
            $entityId = $firstEntityKey !== null ? $entityIdsArray[$firstEntityKey] : 'unknown';

            throw ContentSystemException::layoutAssignmentNotFound(
                $entityType,
                $entityId,
                $context->getSalesChannel()->getId()
            );
        }

        $resolvedData->setResolvedLayoutId($layoutId);

        $renderingContext = RenderingContext::fromRequest($request);

        try {
            $refinedLayout = $this->refinedLayoutBuilder->build($layoutId, $resolvedData, $renderingContext, $context);
        } catch (\Throwable $e) {
            throw ContentSystemException::layoutRefineryFailed($layoutId, $e->getMessage(), $e);
        }

        try {
            $this->hydrationService->hydrate($refinedLayout, $context);
        } catch (\Throwable $e) {
            throw ContentSystemException::hydrationFailed($e->getMessage(), $e);
        }

        $rootElement = $this->applyPartialRendering(
            $refinedLayout->rootElement,
            $renderingContext
        );

        $contentPage = new ContentPage(
            layoutId: $layoutId,
            layout: $rootElement,
            layoutName: $refinedLayout->layoutEntity->getName(),
            layoutVersion: $refinedLayout->layoutEntity->getVersionId(),
            route: $route
        );

        return new ContentRouteResponse($contentPage);
    }

    private function applyPartialRendering(
        ContentElement $rootElement,
        RenderingContext $renderingContext
    ): ContentElement {
        if (!$renderingContext->hasTargetElement()) {
            return $rootElement;
        }

        // Extract target element ID (guaranteed non-null after hasTargetElement check)
        /** @var string $targetElementId */
        $targetElementId = $renderingContext->targetElementId;

        $targetElement = $this->subTreeExtractor->extract($rootElement, $targetElementId);

        if ($targetElement === null) {
            throw ContentSystemException::elementNotFound($targetElementId);
        }

        return $targetElement;
    }
}
