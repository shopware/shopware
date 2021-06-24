<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Review;

use OpenApi\Annotations as OA;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\Events\ProductDetailRouteCacheKeyEvent;
use Shopware\Core\Content\Product\Events\ProductDetailRouteCacheTagsEvent;
use Shopware\Core\Framework\Adapter\Cache\AbstractCacheTracer;
use Shopware\Core\Framework\Adapter\Cache\CacheCompressor;
use Shopware\Core\Framework\Adapter\Cache\CacheStateSubscriber;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\JsonFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\Annotation\Entity;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @RouteScope(scopes={"store-api"})
 */
class CachedProductReviewRoute extends AbstractProductReviewRoute
{
    public const ALL_TAG = 'product-review-route';

    private AbstractProductReviewRoute $decorated;

    private TagAwareAdapterInterface $cache;

    private EntityCacheKeyGenerator $generator;

    /**
     * @var AbstractCacheTracer<ProductReviewRouteResponse>
     */
    private AbstractCacheTracer $tracer;

    private array $states;

    private EventDispatcherInterface $dispatcher;

    private LoggerInterface $logger;

    /**
     * @param AbstractCacheTracer<ProductReviewRouteResponse> $tracer
     */
    public function __construct(
        AbstractProductReviewRoute $decorated,
        TagAwareAdapterInterface $cache,
        EntityCacheKeyGenerator $generator,
        AbstractCacheTracer $tracer,
        EventDispatcherInterface $dispatcher,
        array $states,
        LoggerInterface $logger
    ) {
        $this->decorated = $decorated;
        $this->cache = $cache;
        $this->generator = $generator;
        $this->tracer = $tracer;

        $states[] = CacheStateSubscriber::STATE_LOGGED_IN;
        $this->states = array_unique($states);
        $this->dispatcher = $dispatcher;
        $this->logger = $logger;
    }

    public function getDecorated(): AbstractProductReviewRoute
    {
        return $this->decorated;
    }

    /**
     * @Since("6.3.2.0")
     * @Entity("product_review")
     * @OA\Post(
     *      path="/product/{productId}/reviews",
     *      summary="Fetch product reviews",
     *      description="Perform a filtered search for product reviews.",
     *      operationId="readProductReviews",
     *      tags={"Store API","Product"},
     *      @OA\Parameter(name="Api-Basic-Parameters"),
     *      @OA\Parameter(
     *          name="productId",
     *          description="Identifier of the product.",
     *          @OA\Schema(type="string"),
     *          in="path",
     *          required=true
     *      ),
     *      @OA\Response(
     *          response="200",
     *          description="Entity search result containing product reviews",
     *          @OA\JsonContent(
     *              type="object",
     *              allOf={
     *                  @OA\Schema(ref="#/components/schemas/EntitySearchResult"),
     *                  @OA\Schema(type="object",
     *                      @OA\Property(
     *                          type="array",
     *                          property="elements",
     *                          @OA\Items(ref="#/components/schemas/ProductReview")
     *                      )
     *                  )
     *              }
     *          )
     *     )
     * )
     * @Route("/store-api/product/{productId}/reviews", name="store-api.product-review.list", methods={"POST"})
     */
    public function load(string $productId, Request $request, SalesChannelContext $context, Criteria $criteria): ProductReviewRouteResponse
    {
        if ($context->hasState(...$this->states)) {
            $this->logger->info('cache-miss: ' . self::buildName($productId));

            return $this->getDecorated()->load($productId, $request, $context, $criteria);
        }

        $item = $this->cache->getItem(
            $this->generateKey($productId, $request, $context, $criteria)
        );

        try {
            if ($item->isHit() && $item->get()) {
                $this->logger->info('cache-hit: ' . self::buildName($productId));

                return CacheCompressor::uncompress($item);
            }
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage());
        }

        $this->logger->info('cache-miss: ' . self::buildName($productId));

        $name = self::buildName($productId);
        $response = $this->tracer->trace($name, function () use ($productId, $request, $context, $criteria) {
            return $this->getDecorated()->load($productId, $request, $context, $criteria);
        });

        $item = CacheCompressor::compress($item, $response);

        $item->tag($this->generateTags($productId, $request, $response, $context, $criteria));

        $this->cache->save($item);

        return $response;
    }

    public static function buildName(string $productId): string
    {
        return 'product-review-route-' . $productId;
    }

    private function generateKey(string $productId, Request $request, SalesChannelContext $context, Criteria $criteria): string
    {
        $parts = [
            self::buildName($productId),
            $this->generator->getCriteriaHash($criteria),
            $this->generator->getSalesChannelContextHash($context),
        ];

        $event = new ProductDetailRouteCacheKeyEvent($parts, $request, $context, $criteria);
        $this->dispatcher->dispatch($event);

        return md5(JsonFieldSerializer::encodeJson($event->getParts()));
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
