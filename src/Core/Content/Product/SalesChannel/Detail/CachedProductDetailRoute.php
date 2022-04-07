<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Detail;

use OpenApi\Annotations as OA;
use Shopware\Core\Content\Product\Events\ProductDetailRouteCacheKeyEvent;
use Shopware\Core\Content\Product\Events\ProductDetailRouteCacheTagsEvent;
use Shopware\Core\Framework\Adapter\Cache\AbstractCacheTracer;
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
class CachedProductDetailRoute extends AbstractProductDetailRoute
{
    private AbstractProductDetailRoute $decorated;

    private CacheInterface $cache;

    private EntityCacheKeyGenerator $generator;

    /**
     * @var AbstractCacheTracer<ProductDetailRouteResponse>
     */
    private AbstractCacheTracer $tracer;

    private array $states;

    private EventDispatcherInterface $dispatcher;

    /**
     * @param AbstractCacheTracer<ProductDetailRouteResponse> $tracer
     */
    public function __construct(
        AbstractProductDetailRoute $decorated,
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

    public function getDecorated(): AbstractProductDetailRoute
    {
        return $this->decorated;
    }

    /**
     * @Since("6.3.2.0")
     * @Entity("product")
     * @OA\Post(
     *      path="/product/{productId}",
     *      summary="Fetch a single product",
     *      description="This route is used to load a single product with the corresponding details. In addition to loading the data, the best variant of the product is determined when a parent id is passed.",
     *      operationId="readProductDetail",
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
     *          description="Product information along with variant groups and options",
     *          @OA\JsonContent(ref="#/components/schemas/ProductDetailResponse")
     *     )
     * )
     * @Route("/store-api/product/{productId}", name="store-api.product.detail", methods={"POST"})
     */
    public function load(string $productId, Request $request, SalesChannelContext $context, Criteria $criteria): ProductDetailRouteResponse
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

    public static function buildName(string $parentId): string
    {
        return 'product-detail-route-' . $parentId;
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

    private function generateTags(string $productId, Request $request, ProductDetailRouteResponse $response, SalesChannelContext $context, Criteria $criteria): array
    {
        $parentId = $response->getProduct()->getParentId() ?? $response->getProduct()->getId();

        $pageId = $response->getProduct()->getCmsPageId();

        $tags = array_merge(
            $this->tracer->get(self::buildName($productId)),
            [$pageId !== null ? EntityCacheKeyGenerator::buildCmsTag($pageId) : null],
            [self::buildName($parentId)]
        );

        $event = new ProductDetailRouteCacheTagsEvent($tags, $request, $response, $context, $criteria);
        $this->dispatcher->dispatch($event);

        return array_unique(array_filter($event->getTags()));
    }
}
