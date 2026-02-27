<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\SalesChannel;

use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\ContentSystem\Adapter\RenderingSpecificationResolver;
use Shopware\Core\Framework\ContentSystem\Cache\CacheFinalizer;
use Shopware\Core\Framework\ContentSystem\Cache\RenderingCacheContext;
use Shopware\Core\Framework\ContentSystem\ContentPipeline;
use Shopware\Core\Framework\ContentSystem\ContentSection;
use Shopware\Core\Framework\ContentSystem\Output\Format\AbstractResponseFactory;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @final
 */
#[Package('discovery')]
class ContentRoute extends AbstractContentRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly RenderingSpecificationResolver $specificationResolver,
        private readonly ContentSection $section,
        private readonly CacheTagCollector $cacheTagCollector,
        private readonly AbstractResponseFactory $responseFactory,
        private readonly ContentPipeline $contentPipeline,
        private readonly CacheFinalizer $cacheFinalizer,
    ) {
    }

    public function getDecorated(): AbstractContentRoute
    {
        throw new DecorationPatternException(self::class);
    }

    public function load(string $path, Request $request, SalesChannelContext $context): AbstractContentRouteResponse
    {
        $renderingSpecification = $this->specificationResolver->resolve($path, $request, $context);

        foreach ($this->section->buildRouteCacheTags($renderingSpecification->layoutId) as $tag) {
            $this->cacheTagCollector->addTag($tag);
        }

        $cacheContext = new RenderingCacheContext();
        $cacheContext->addTags($renderingSpecification->cacheTags);

        $contentPage = $this->contentPipeline->load($renderingSpecification, $cacheContext, $this->responseFactory->getRenderingMode(), $context);

        $this->cacheFinalizer->finalize($request, $cacheContext);

        return $this->responseFactory->createResponse($contentPage);
    }
}
