<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Listing;

use OpenApi\Annotations as OA;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\Events\ProductListingRouteCacheKeyEvent;
use Shopware\Core\Content\Product\Events\ProductListingRouteCacheTagsEvent;
use Shopware\Core\Framework\Adapter\Cache\AbstractCacheTracer;
use Shopware\Core\Framework\Adapter\Cache\CacheCompressor;
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
class CachedProductListingRoute extends AbstractProductListingRoute
{
    private AbstractProductListingRoute $decorated;

    private TagAwareAdapterInterface $cache;

    private EntityCacheKeyGenerator $generator;

    /**
     * @var AbstractCacheTracer<ProductListingRouteResponse>
     */
    private AbstractCacheTracer $tracer;

    private array $states;

    private EventDispatcherInterface $dispatcher;

    private LoggerInterface $logger;

    /**
     * @param AbstractCacheTracer<ProductListingRouteResponse> $tracer
     */
    public function __construct(
        AbstractProductListingRoute $decorated,
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
        $this->states = $states;
        $this->dispatcher = $dispatcher;
        $this->logger = $logger;
    }

    public function getDecorated(): AbstractProductListingRoute
    {
        return $this->decorated;
    }

    /**
     * @Since("6.2.0.0")
     * @Entity("product")
     * @OA\Post(
     *      path="/product-listing/{categoryId}",
     *      summary="Fetch a product listing by category",
     *      description="Fetches a product listing for a specific category. It also provides filters, sortings and property aggregations, analogous to the /search endpoint.",
     *      operationId="readProductListing",
     *      tags={"Store API","Product"},
     *      @OA\Parameter(
     *          name="categoryId",
     *          description="Identifier of a category.",
     *          @OA\Schema(type="string"),
     *          in="path",
     *          required=true
     *      ),
     *      @OA\Response(
     *          response="200",
     *          description="Returns a product listing containing all products and additional fields to display a listing.",
     *          @OA\JsonContent(ref="#/components/schemas/ProductListingResult")
     *     )
     * )
     * @Route("/store-api/product-listing/{categoryId}", name="store-api.product.listing", methods={"POST"})
     */
    public function load(string $categoryId, Request $request, SalesChannelContext $context, Criteria $criteria): ProductListingRouteResponse
    {
        if ($context->hasState(...$this->states)) {
            $this->logger->info('cache-miss: ' . self::buildName($categoryId));

            return $this->getDecorated()->load($categoryId, $request, $context, $criteria);
        }

        $item = $this->cache->getItem(
            $this->generateKey($categoryId, $request, $context, $criteria)
        );

        try {
            if ($item->isHit() && $item->get()) {
                $this->logger->info('cache-hit: ' . self::buildName($categoryId));

                return CacheCompressor::uncompress($item);
            }
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage());
        }

        $this->logger->info('cache-miss: ' . self::buildName($categoryId));

        $name = self::buildName($categoryId);

        $response = $this->tracer->trace($name, function () use ($categoryId, $request, $context, $criteria) {
            return $this->getDecorated()->load($categoryId, $request, $context, $criteria);
        });

        $item = CacheCompressor::compress($item, $response);

        $item->tag($this->generateTags($categoryId, $request, $response, $context, $criteria));

        $this->cache->save($item);

        return $response;
    }

    public static function buildName(string $categoryId): string
    {
        return 'product-listing-route-' . $categoryId;
    }

    private function generateKey(string $categoryId, Request $request, SalesChannelContext $context, Criteria $criteria): string
    {
        $parts = [
            self::buildName($categoryId),
            $this->generator->getCriteriaHash($criteria),
            $this->generator->getSalesChannelContextHash($context),
        ];

        $event = new ProductListingRouteCacheKeyEvent($parts, $categoryId, $request, $context, $criteria);
        $this->dispatcher->dispatch($event);

        return md5(JsonFieldSerializer::encodeJson($event->getParts()));
    }

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
