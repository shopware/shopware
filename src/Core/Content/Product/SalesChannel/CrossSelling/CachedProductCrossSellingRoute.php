<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\CrossSelling;

use Shopware\Core\Content\Product\Events\CrossSellingRouteCacheKeyEvent;
use Shopware\Core\Content\Product\Events\CrossSellingRouteCacheTagsEvent;
use Shopware\Core\Framework\Adapter\Cache\AbstractCacheTracer;
use Shopware\Core\Framework\Adapter\Cache\CacheValueCompressor;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RuleAreas;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\JsonFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\Annotation\Entity;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @Route(defaults={"_routeScope"={"store-api"}})
 *
 * @package inventory
 */
class CachedProductCrossSellingRoute extends AbstractProductCrossSellingRoute
{
    private AbstractProductCrossSellingRoute $decorated;

    private CacheInterface $cache;

    private EntityCacheKeyGenerator $generator;

    /**
     * @var AbstractCacheTracer<ProductCrossSellingRouteResponse>
     */
    private AbstractCacheTracer $tracer;

    /**
     * @var array<string>
     */
    private array $states;

    private EventDispatcherInterface $dispatcher;

    /**
     * @internal
     *
     * @param AbstractCacheTracer<ProductCrossSellingRouteResponse> $tracer
     * @param array<string> $states
     */
    public function __construct(
        AbstractProductCrossSellingRoute $decorated,
        CacheInterface $cache,
        EntityCacheKeyGenerator $generator,
        AbstractCacheTracer $tracer,
        EventDispatcherInterface $dispatcher,
        array $states
    ) {
        $this->decorated = $decorated;
        $this->cache = $cache;
        $this->generator = $generator;
        $this->tracer = $tracer;
        $this->states = $states;
        $this->dispatcher = $dispatcher;
    }

    public function getDecorated(): AbstractProductCrossSellingRoute
    {
        return $this->decorated;
    }

    public static function buildName(string $id): string
    {
        return 'cross-selling-route-' . $id;
    }

    /**
     * @Since("6.3.2.0")
     * @Entity("product")
     * @Route("/store-api/product/{productId}/cross-selling", name="store-api.product.cross-selling", methods={"POST"})
     */
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

            $response = $this->tracer->trace($name, function () use ($productId, $request, $context, $criteria) {
                return $this->getDecorated()->load($productId, $request, $context, $criteria);
            });

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

        return self::buildName($productId) . '-' . md5(JsonFieldSerializer::encodeJson($event->getParts()));
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

        return array_map([EntityCacheKeyGenerator::class, 'buildStreamTag'], $ids);
    }

    /**
     * @return array<string>
     */
    private function extractProductIds(ProductCrossSellingRouteResponse $response): array
    {
        $ids = [];

        foreach ($response->getResult() as $element) {
            $ids = array_merge($ids, $element->getProducts()->getIds());
        }

        $ids = array_unique(array_filter($ids));

        return array_map([EntityCacheKeyGenerator::class, 'buildProductTag'], $ids);
    }
}
