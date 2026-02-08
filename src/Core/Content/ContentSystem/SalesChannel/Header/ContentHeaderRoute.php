<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\SalesChannel\Header;

use Shopware\Core\Content\ContentSystem\Adapter\HeaderSpecificationFactory;
use Shopware\Core\Content\ContentSystem\Cache\CacheFinalizer;
use Shopware\Core\Content\ContentSystem\ContentPipeline;
use Shopware\Core\Content\ContentSystem\RenderingCacheContext;
use Shopware\Core\Content\ContentSystem\RenderingMode;
use Shopware\Core\Content\ContentSystem\SalesChannel\ContentRoute;
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
class ContentHeaderRoute extends AbstractContentHeaderRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly HeaderSpecificationFactory $specificationFactory,
        private readonly ContentPipeline $contentPipeline,
        private readonly CacheTagCollector $cacheTagCollector,
        private readonly CacheFinalizer $cacheFinalizer,
    ) {
    }

    public static function buildLayoutTag(string $layoutId): string
    {
        return 'header-content-layout-' . $layoutId;
    }

    public function getDecorated(): AbstractContentHeaderRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/content-header',
        name: 'store-api.content-header',
        defaults: [
            PlatformRequest::ATTRIBUTE_HTTP_CACHE => true,
            'excludes' => [
                'content_element' => [
                    'dataRequirements',
                    'contextDefinitions',
                ],
            ],
        ],
        methods: ['GET']
    )]
    public function load(Request $request, SalesChannelContext $context): ContentHeaderRouteResponse
    {
        $renderingSpecification = $this->specificationFactory->create($request, $context);

        $this->cacheTagCollector->addTag(ContentRoute::buildLayoutTag($renderingSpecification->layoutId));
        $this->cacheTagCollector->addTag(self::buildLayoutTag($renderingSpecification->layoutId));

        $cacheContext = new RenderingCacheContext();
        $cacheContext->addTags($renderingSpecification->cacheTags);

        $contentPage = $this->contentPipeline->load($renderingSpecification, $cacheContext, RenderingMode::FULL, $context);

        $this->cacheFinalizer->finalize($request, $cacheContext);

        return new ContentHeaderRouteResponse($contentPage);
    }
}
