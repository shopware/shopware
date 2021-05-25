<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\CrossSelling;

use OpenApi\Annotations as OA;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\Events\CrossSellingRouteCacheKeyEvent;
use Shopware\Core\Content\Product\Events\CrossSellingRouteCacheTagsEvent;
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
class CachedProductCrossSellingRoute extends AbstractProductCrossSellingRoute
{
    private AbstractProductCrossSellingRoute $decorated;

    private TagAwareAdapterInterface $cache;

    private EntityCacheKeyGenerator $generator;

    /**
     * @var AbstractCacheTracer<ProductCrossSellingRouteResponse>
     */
    private AbstractCacheTracer $tracer;

    private array $states;

    private EventDispatcherInterface $dispatcher;

    private LoggerInterface $logger;

    /**
     * @param AbstractCacheTracer<ProductCrossSellingRouteResponse> $tracer
     */
    public function __construct(
        AbstractProductCrossSellingRoute $decorated,
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
     * @OA\Post(
     *      path="/product/{productId}/cross-selling",
     *      summary="Fetch cross-selling groups of a product",
     *      description="This route is used to load the cross sellings for a product. A product has several cross selling definitions in which several products are linked. The route returns the cross sellings together with the linked products",
     *      operationId="readProductCrossSellings",
     *      tags={"Store API","Product"},
     *      @OA\Parameter(
     *          name="productId",
     *          description="Product ID",
     *          @OA\Schema(type="string"),
     *          in="path",
     *          required=true
     *      ),
     *      @OA\Response(
     *          response="200",
     *          description="Found cross sellings",
     *          @OA\JsonContent(ref="#/components/schemas/CrossSellingElementCollection")
     *     )
     * )
     * @Route("/store-api/product/{productId}/cross-selling", name="store-api.product.cross-selling", methods={"POST"})
     */
    public function load(string $productId, Request $request, SalesChannelContext $context, Criteria $criteria): ProductCrossSellingRouteResponse
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

    private function generateKey(string $productId, Request $request, SalesChannelContext $context, Criteria $criteria): string
    {
        $parts = [
            self::buildName($productId),
            $this->generator->getCriteriaHash($criteria),
            $this->generator->getSalesChannelContextHash($context),
        ];

        $event = new CrossSellingRouteCacheKeyEvent($productId, $parts, $request, $context, $criteria);
        $this->dispatcher->dispatch($event);

        return md5(JsonFieldSerializer::encodeJson($event->getParts()));
    }

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

    private function extractStreamTags(ProductCrossSellingRouteResponse $response): array
    {
        $ids = [];

        foreach ($response->getResult() as $element) {
            $ids[] = $element->getStreamId();
        }

        $ids = array_unique(array_filter($ids));

        return array_map([EntityCacheKeyGenerator::class, 'buildStreamTag'], $ids);
    }

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
