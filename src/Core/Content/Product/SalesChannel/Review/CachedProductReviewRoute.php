<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Review;

use Shopware\Core\Content\Product\Events\ProductDetailRouteCacheKeyEvent;
use Shopware\Core\Content\Product\Events\ProductDetailRouteCacheTagsEvent;
use Shopware\Core\Framework\Adapter\Cache\AbstractCacheTracer;
use Shopware\Core\Framework\Adapter\Cache\CacheStateSubscriber;
use Shopware\Core\Framework\Adapter\Cache\CacheValueCompressor;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
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
 */
class CachedProductReviewRoute extends AbstractProductReviewRoute
{
    public const ALL_TAG = 'product-review-route';

    private AbstractProductReviewRoute $decorated;

    private CacheInterface $cache;

    private EntityCacheKeyGenerator $generator;

    /**
     * @var AbstractCacheTracer<ProductReviewRouteResponse>
     */
    private AbstractCacheTracer $tracer;

    private array $states;

    private EventDispatcherInterface $dispatcher;

    /**
     * @internal
     *
     * @param AbstractCacheTracer<ProductReviewRouteResponse> $tracer
     */
    public function __construct(
        AbstractProductReviewRoute $decorated,
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

        $states[] = CacheStateSubscriber::STATE_LOGGED_IN;
        $this->states = array_unique($states);
        $this->dispatcher = $dispatcher;
    }

    public function getDecorated(): AbstractProductReviewRoute
    {
        return $this->decorated;
    }

    /**
     * @Since("6.3.2.0")
     * @Entity("product_review")
     * @Route("/store-api/product/{productId}/reviews", name="store-api.product-review.list", methods={"POST"})
     */
    public function load(string $productId, Request $request, SalesChannelContext $context, Criteria $criteria): ProductReviewRouteResponse
    {
        if ($context->hasState(...$this->states)) {
            return $this->getDecorated()->load($productId, $request, $context, $criteria);
        }

        $key = $this->generateKey($productId, $request, $context, $criteria);

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

    public static function buildName(string $productId): string
    {
        return 'product-review-route-' . $productId;
    }

    private function generateKey(string $productId, Request $request, SalesChannelContext $context, Criteria $criteria): string
    {
        $parts = [
            $this->generator->getCriteriaHash($criteria),
            $this->generator->getSalesChannelContextHash($context),
        ];

        $event = new ProductDetailRouteCacheKeyEvent($parts, $request, $context, $criteria);
        $this->dispatcher->dispatch($event);

        return self::buildName($productId) . '-' . md5(JsonFieldSerializer::encodeJson($event->getParts()));
    }

    private function generateTags(string $productId, Request $request, ProductReviewRouteResponse $response, SalesChannelContext $context, Criteria $criteria): array
    {
        $tags = array_merge(
            $this->tracer->get(self::buildName($productId)),
            [self::buildName($productId)]
        );

        $event = new ProductDetailRouteCacheTagsEvent($tags, $request, $response, $context, $criteria);
        $this->dispatcher->dispatch($event);

        return array_unique(array_filter($event->getTags()));
    }
}
