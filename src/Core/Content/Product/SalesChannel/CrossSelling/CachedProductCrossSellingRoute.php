<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\CrossSelling;

use Shopware\Core\Content\Product\Events\CrossSellingRouteCacheKeyEvent;
use Shopware\Core\Content\Product\Events\CrossSellingRouteCacheTagsEvent;
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
class CachedProductCrossSellingRoute extends AbstractProductCrossSellingRoute
{
    /**
     * @internal
     *
     * @param AbstractCacheTracer<ProductCrossSellingRouteResponse> $tracer
     * @param array<string> $states
     */
    public function __construct(
        private readonly AbstractProductCrossSellingRoute $decorated,
        private readonly CacheInterface $cache,
        private readonly EntityCacheKeyGenerator $generator,
        private readonly AbstractCacheTracer $tracer,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly array $states
    ) {
    }

    public function getDecorated(): AbstractProductCrossSellingRoute
    {
        return $this->decorated;
    }

    public static function buildName(string $id): string
    {
        return 'cross-selling-route-' . $id;
    }

    #[Route(path: '/store-api/product/{productId}/cross-selling', name: 'store-api.product.cross-selling', methods: ['POST'], defaults: ['_entity' => 'product'])]
    public function load(string $productId, Request $request, SalesChannelContext $context, Criteria $criteria): ProductCrossSellingRouteResponse
    {
        if ($context->hasState(...$this->states)) {
            return $this->getDecorated()->load($productId, $request, $context, $criteria);
        }

        $key = $this->generateKey($productId, $request, $context, $criteria);

        if ($key === null) {
            return $this->getDecorated()->load($productId, $request, $context, $criteria);
        }

        $value = $this->cache->get($key, function (ItemInterface $item) use ($productId, $request, $context, $criteria) {
            $name = self::buildName($productId);

            $response = $this->tracer->trace($name, fn () => $this->getDecorated()->load($productId, $request, $context, $criteria));

            $item->tag($this->generateTags($productId, $request, $response, $context, $criteria));

            return CacheValueCompressor::compress($response);
        });

        return CacheValueCompressor::uncompress($value);
    }

    private function generateKey(string $productId, Request $request, SalesChannelContext $context, Criteria $criteria): ?string
    {
        $parts = [
            $this->generator->getCriteriaHash($criteria),
            $this->generator->getSalesChannelContextHash($context, [RuleAreas::PRODUCT_AREA]),
        ];

        $event = new CrossSellingRouteCacheKeyEvent($productId, $parts, $request, $context, $criteria);
        $this->dispatcher->dispatch($event);

        if (!$event->shouldCache()) {
            return null;
        }

        return self::buildName($productId) . '-' . md5(Json::encode($event->getParts()));
    }

    /**
     * @return array<string>
     */
    private function generateTags(string $productId, Request $request, ProductCrossSellingRouteResponse $response, SalesChannelContext $context, Criteria $criteria): array
    {
        $tags = array_merge(
            $this->tracer->get(self::buildName($productId)),
            $this->extractStreamTags($response),
            $this->extractProductIds($response),
            [self::buildName($productId)]
        );

        $event = new CrossSellingRouteCacheTagsEvent($productId, $tags, $request, $response, $context, $criteria);
        $this->dispatcher->dispatch($event);

        return array_unique(array_filter($event->getTags()));
    }

    /**
     * @return array<string>
     */
    private function extractStreamTags(ProductCrossSellingRouteResponse $response): array
    {
        $ids = [];

        foreach ($response->getResult() as $element) {
            $ids[] = $element->getStreamId();
        }

        $ids = array_unique(array_filter($ids));

        return array_map(EntityCacheKeyGenerator::buildStreamTag(...), $ids);
    }

    /**
     * @return array<string>
     */
    private function extractProductIds(ProductCrossSellingRouteResponse $response): array
    {
        $ids = [];

        foreach ($response->getResult() as $element) {
            $ids = [...$ids, ...$element->getProducts()->getIds()];
        }

        $ids = array_unique(array_filter($ids));

        return array_map(EntityCacheKeyGenerator::buildProductTag(...), $ids);
    }
}
