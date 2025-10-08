<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\SalesChannel;

use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Content\ContentSystem\Hydration\ContentElementHydrator;
use Shopware\Core\Content\ContentSystem\Layout\Refinery\RefinedLayoutBuilder;
use Shopware\Core\Content\ContentSystem\Routing\IdResolution\EntityIdResolver;
use Shopware\Core\Content\ContentSystem\Routing\LayoutResolution\LayoutResolver;
use Shopware\Core\Content\ContentSystem\Routing\Router\ContentRouter;
use Shopware\Core\Content\ContentSystem\SalesChannel\Struct\ContentPage;
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
        private readonly ContentElementHydrator $hydrationService
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

        $layoutId = $route->getLayoutId();

        if ($layoutId === null) {
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
        }

        try {
            $refinedLayout = $this->refinedLayoutBuilder->build($layoutId, $resolvedData, $context);
        } catch (\Throwable $e) {
            throw ContentSystemException::layoutRefineryFailed($layoutId, $e->getMessage(), $e);
        }

        try {
            $this->hydrationService->hydrate($refinedLayout, $context);
        } catch (\Throwable $e) {
            throw ContentSystemException::hydrationFailed($e->getMessage(), $e);
        }

        $contentPage = new ContentPage(
            layoutId: $layoutId,
            layout: $refinedLayout->rootElement,
            layoutName: $refinedLayout->layoutEntity->getName(),
            layoutVersion: $refinedLayout->layoutEntity->getVersionId(),
            route: $route
        );

        return new ContentRouteResponse($contentPage);
    }
}
