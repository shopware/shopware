<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Review;

use Shopware\Core\Content\Product\Events\ProductDetailRouteCacheKeyEvent;
use Shopware\Core\Content\Product\Events\ProductDetailRouteCacheTagsEvent;
use Shopware\Core\Framework\Adapter\Cache\AbstractCacheTracer;
use Shopware\Core\Framework\Adapter\Cache\CacheStateSubscriber;
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
class CachedProductReviewRoute extends AbstractProductReviewRoute
{
    final public const ALL_TAG = 'product-review-route';

    /**
     * @var string[]
     */
    private readonly array $states;

    /**
     * @internal
     *
     * @param AbstractCacheTracer<ProductReviewRouteResponse> $tracer
     * @param string[] $states
     */
    public function __construct(
        private readonly AbstractProductReviewRoute $decorated,
        private readonly CacheInterface $cache,
        private readonly EntityCacheKeyGenerator $generator,
        private readonly AbstractCacheTracer $tracer,
        private readonly EventDispatcherInterface $dispatcher,
        array $states
    ) {
        $states[] = CacheStateSubscriber::STATE_LOGGED_IN;
        $this->states = array_unique($states);
    }

    public function getDecorated(): AbstractProductReviewRoute
    {
        return $this->decorated;
    }

    #[Route(path: '/store-api/product/{productId}/reviews', name: 'store-api.product-review.list', methods: ['POST'], defaults: ['_entity' => 'product_review'])]
    public function load(string $productId, Request $request, SalesChannelContext $context, Criteria $criteria): ProductReviewRouteResponse
    {
        if ($context->hasState(...$this->states)) {
            return $this->getDecorated()->load($productId, $request, $context, $criteria);
        }

        $key = $this->generateKey($productId, $request, $context, $criteria);

        $value = $this->cache->get($key, function (ItemInterface $item) use ($productId, $request, $context, $criteria) {
            $name = self::buildName($productId);
            $response = $this->tracer->trace($name, fn () => $this->getDecorated()->load($productId, $request, $context, $criteria));

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
            $this->generator->getSalesChannelContextHash($context, [RuleAreas::PRODUCT_AREA]),
        ];

        $event = new ProductDetailRouteCacheKeyEvent($parts, $request, $context, $criteria);
        $this->dispatcher->dispatch($event);

        return self::buildName($productId) . '-' . md5(Json::encode($event->getParts()));
    }

    /**
     * @return string[]
     */
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
