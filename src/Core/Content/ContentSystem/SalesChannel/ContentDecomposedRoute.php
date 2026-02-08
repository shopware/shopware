<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\SalesChannel;

use Shopware\Core\Content\ContentSystem\Cache\CacheFinalizer;
use Shopware\Core\Content\ContentSystem\ContentPipeline;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Content\ContentSystem\RenderingCacheContext;
use Shopware\Core\Content\ContentSystem\RenderingMode;
use Shopware\Core\Content\ContentSystem\RenderingSpecificationResolver;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
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
class ContentDecomposedRoute extends AbstractContentDecomposedRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly RenderingSpecificationResolver $specificationResolver,
        private readonly ContentPipeline $contentPipeline,
        private readonly DataLoaderConfigSerializerProvider $configSerializerProvider,
        private readonly CacheTagCollector $cacheTagCollector,
        private readonly CacheFinalizer $cacheFinalizer,
    ) {
    }

    public function getDecorated(): AbstractContentDecomposedRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/content-decomposed/{path}',
        name: 'store-api.content.decomposed',
        requirements: ['path' => '.+'],
        defaults: [
            PlatformRequest::ATTRIBUTE_HTTP_CACHE => true,
            'excludes' => [
                'content_element' => [
                    'dataRequirements',
                    'properties',
                    'contextDefinitions',
                ],
            ],
        ],
        methods: ['GET']
    )]
    public function load(string $path, Request $request, SalesChannelContext $context): ContentDecomposedRouteResponse
    {
        $renderingSpecification = $this->specificationResolver->resolve($path, $request, $context);

        $this->cacheTagCollector->addTag(ContentRoute::buildLayoutTag($renderingSpecification->layoutId));

        $cacheContext = new RenderingCacheContext();
        $cacheContext->addTags($renderingSpecification->cacheTags);

        $contentPage = $this->contentPipeline->load($renderingSpecification, $cacheContext, RenderingMode::FULL, $context);

        $this->cacheFinalizer->finalize($request, $cacheContext);

        return new ContentDecomposedRouteResponse($contentPage->getContentDecomposedPage($this->configSerializerProvider));
    }
}
