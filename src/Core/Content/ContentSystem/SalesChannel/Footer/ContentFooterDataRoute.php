<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\SalesChannel\Footer;

use Shopware\Core\Content\ContentSystem\Adapter\FooterSpecificationFactory;
use Shopware\Core\Content\ContentSystem\Cache\CacheFinalizer;
use Shopware\Core\Content\ContentSystem\ContentPipeline;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
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
 * Returns footer content data and assignments without skeleton structure.
 *
 * Runs full pipeline (including hydration) but returns only the data
 * and element-to-property assignments. Useful when client already has
 * the skeleton and only needs refreshed data.
 *
 * @final
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
#[Package('discovery')]
class ContentFooterDataRoute extends AbstractContentFooterDataRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly FooterSpecificationFactory $specificationFactory,
        private readonly ContentPipeline $contentPipeline,
        private readonly DataLoaderConfigSerializerProvider $configSerializerProvider,
        private readonly CacheTagCollector $cacheTagCollector,
        private readonly CacheFinalizer $cacheFinalizer,
    ) {
    }

    public function getDecorated(): AbstractContentFooterDataRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/content-footer-data',
        name: 'store-api.content-footer.data',
        defaults: [
            '_httpCache' => true,
        ],
        methods: ['GET']
    )]
    public function load(Request $request, SalesChannelContext $context): ContentFooterDataRouteResponse
    {
        $renderingSpecification = $this->specificationFactory->create($request, $context);

        $this->cacheTagCollector->addTag(ContentRoute::buildLayoutTag($renderingSpecification->layoutId));
        $this->cacheTagCollector->addTag(ContentFooterRoute::buildLayoutTag($renderingSpecification->layoutId));

        $cacheContext = new RenderingCacheContext();
        $cacheContext->addTags($renderingSpecification->cacheTags);

        $contentPage = $this->contentPipeline->load($renderingSpecification, $cacheContext, RenderingMode::FULL, $context);

        $this->cacheFinalizer->finalize($request, $cacheContext);

        return new ContentFooterDataRouteResponse($contentPage->getContentDataPage($this->configSerializerProvider));
    }
}
