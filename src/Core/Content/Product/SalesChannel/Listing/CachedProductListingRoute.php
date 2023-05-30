<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Listing;

use Shopware\Core\Content\Product\Events\ProductListingRouteCacheKeyEvent;
use Shopware\Core\Content\Product\Events\ProductListingRouteCacheTagsEvent;
use Shopware\Core\Framework\Adapter\Cache\AbstractCacheTracer;
use Shopware\Core\Framework\Adapter\Cache\CacheValueCompressor;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RuleAreas;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Json;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(defaults: ['_routeScope' => ['store-api']])]
#[Package('inventory')]
class CachedProductListingRoute extends AbstractProductListingRoute
{
    /**
     * @internal
     *
     * @param AbstractCacheTracer<ProductListingRouteResponse> $tracer
     * @param array<string> $states
     */
    public function __construct(
        private readonly AbstractProductListingRoute $decorated,
        private readonly CacheInterface $cache,
        private readonly EntityCacheKeyGenerator $generator,
        private readonly AbstractCacheTracer $tracer,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly array $states
    ) {
    }

    public function getDecorated(): AbstractProductListingRoute
    {
        return $this->decorated;
    }

    #[Route(path: '/store-api/product-listing/{categoryId}', name: 'store-api.product.listing', methods: ['POST'], defaults: ['_entity' => 'product'])]
    public function load(string $categoryId, Request $request, SalesChannelContext $context, Criteria $criteria): ProductListingRouteResponse
    {
        if ($context->hasState(...$this->states)) {
            return $this->getDecorated()->load($categoryId, $request, $context, $criteria);
        }

        $key = $this->generateKey($categoryId, $request, $context, $criteria);

        if ($key === null) {
            return $this->getDecorated()->load($categoryId, $request, $context, $criteria);
        }

        $value = $this->cache->get($key, function (ItemInterface $item) use ($categoryId, $request, $context, $criteria) {
            $name = self::buildName($categoryId);

            $response = $this->tracer->trace($name, fn () => $this->getDecorated()->load($categoryId, $request, $context, $criteria));

            $item->tag($this->generateTags($categoryId, $request, $response, $context, $criteria));

            return CacheValueCompressor::compress($response);
        });

        return CacheValueCompressor::uncompress($value);
    }

    public static function buildName(string $categoryId): string
    {
        return 'product-listing-route-' . $categoryId;
    }

    private function generateKey(string $categoryId, Request $request, SalesChannelContext $context, Criteria $criteria): ?string
    {
        $parts = [
            $this->generator->getCriteriaHash($criteria),
            $this->generator->getSalesChannelContextHash($context, [RuleAreas::PRODUCT_AREA, RuleAreas::CATEGORY_AREA]),
        ];

        $event = new ProductListingRouteCacheKeyEvent($parts, $categoryId, $request, $context, $criteria);
        $this->dispatcher->dispatch($event);

        if (!$event->shouldCache()) {
            return null;
        }

        return self::buildName($categoryId) . '-' . md5(Json::encode($event->getParts()));
    }

    /**
     * @return array<string>
     */
    private function generateTags(string $categoryId, Request $request, ProductListingRouteResponse $response, SalesChannelContext $context, Criteria $criteria): array
    {
        $streamId = $response->getResult()->getStreamId();

        $tags = array_merge(
            $this->tracer->get(self::buildName($categoryId)),
            [$streamId ? EntityCacheKeyGenerator::buildStreamTag($streamId) : null],
            [self::buildName($categoryId)]
        );

        $event = new ProductListingRouteCacheTagsEvent($tags, $categoryId, $request, $response, $context, $criteria);
        $this->dispatcher->dispatch($event);

        return array_unique(array_filter($event->getTags()));
    }
}
