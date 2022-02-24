<?php declare(strict_types=1);

namespace Shopware\Core\Content\LandingPage\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ProductBoxStruct;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ProductSliderStruct;
use Shopware\Core\Content\LandingPage\Event\LandingPageRouteCacheKeyEvent;
use Shopware\Core\Content\LandingPage\Event\LandingPageRouteCacheTagsEvent;
use Shopware\Core\Framework\Adapter\Cache\AbstractCacheTracer;
use Shopware\Core\Framework\Adapter\Cache\CacheValueCompressor;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\JsonFieldSerializer;
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
class CachedLandingPageRoute extends AbstractLandingPageRoute
{
    private AbstractLandingPageRoute $decorated;

    private CacheInterface $cache;

    private EntityCacheKeyGenerator $generator;

    /**
     * @var AbstractCacheTracer<LandingPageRouteResponse>
     */
    private AbstractCacheTracer $tracer;

    private array $states;

    private EventDispatcherInterface $dispatcher;

    /**
     * @param AbstractCacheTracer<LandingPageRouteResponse> $tracer
     */
    public function __construct(
        AbstractLandingPageRoute $decorated,
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

    public static function buildName(string $id): string
    {
        return 'landing-page-route-' . $id;
    }

    public function getDecorated(): AbstractLandingPageRoute
    {
        return $this->decorated;
    }

    /**
     * @Since("6.4.0.0")
     * @OA\Post(
     *      path="/landing-page/{landingPageId}",
     *      summary="Fetch a landing page with the resolved CMS page",
     *      description="Loads a landing page by its identifier and resolves the CMS page.

**Important notice**

The criteria passed with this route also affects the listing, if there is one within the cms page.",
     *      operationId="readLandingPage",
     *      tags={"Store API", "Content"},
     *      @OA\Parameter(name="Api-Basic-Parameters"),
     *      @OA\Parameter(
     *          name="landingPageId",
     *          description="Identifier of the landing page.",
     *          @OA\Schema(type="string"),
     *          in="path",
     *          required=true
     *      ),
     *      @OA\RequestBody(
     *          @OA\JsonContent(
     *              @OA\Property(
     *                  property="slots",
     *                  description="Resolves only the given slot identifiers. The identifiers have to be seperated by a '|' character.",
     *                  type="string"
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response="200",
     *          description="The loaded landing page with cms page",
     *          @OA\JsonContent(ref="#/components/schemas/LandingPage")
     *     ),
     *     @OA\Response(
     *          response="404",
     *          ref="#/components/responses/404"
     *     ),
     * )
     *
     * @Route("/store-api/landing-page/{landingPageId}", name="store-api.landing-page.detail", methods={"POST"})
     */
    public function load(string $landingPageId, Request $request, SalesChannelContext $context): LandingPageRouteResponse
    {
        if ($context->hasState(...$this->states)) {
            return $this->getDecorated()->load($landingPageId, $request, $context);
        }

        $key = $this->generateKey($landingPageId, $request, $context);

        $value = $this->cache->get($key, function (ItemInterface $item) use ($request, $context, $landingPageId) {
            $name = self::buildName($landingPageId);
            $response = $this->tracer->trace($name, function () use ($landingPageId, $request, $context) {
                return $this->getDecorated()->load($landingPageId, $request, $context);
            });

            $item->tag($this->generateTags($landingPageId, $response, $request, $context));

            return CacheValueCompressor::compress($response);
        });

        return CacheValueCompressor::uncompress($value);
    }

    private function generateKey(string $landingPageId, Request $request, SalesChannelContext $context): string
    {
        $parts = array_merge(
            $request->query->all(),
            $request->request->all(),
            [$this->generator->getSalesChannelContextHash($context)]
        );

        $event = new LandingPageRouteCacheKeyEvent($landingPageId, $parts, $request, $context, null);
        $this->dispatcher->dispatch($event);

        return self::buildName($landingPageId) . '-' . md5(JsonFieldSerializer::encodeJson($event->getParts()));
    }

    private function generateTags(string $landingPageId, LandingPageRouteResponse $response, Request $request, SalesChannelContext $context): array
    {
        $tags = array_merge(
            $this->tracer->get(self::buildName($landingPageId)),
            $this->extractIds($response),
            [self::buildName($landingPageId)]
        );

        $event = new LandingPageRouteCacheTagsEvent($landingPageId, $tags, $request, $response, $context, null);
        $this->dispatcher->dispatch($event);

        return array_unique(array_filter($event->getTags()));
    }

    private function extractIds(LandingPageRouteResponse $response): array
    {
        $page = $response->getLandingPage()->getCmsPage();

        if ($page === null) {
            return [];
        }

        $ids = [];

        $slots = $page->getElementsOfType('product-slider');
        /** @var CmsSlotEntity $slot */
        foreach ($slots as $slot) {
            $slider = $slot->getData();

            if (!$slider instanceof ProductSliderStruct) {
                continue;
            }

            if ($slider->getProducts() === null) {
                continue;
            }
            foreach ($slider->getProducts() as $product) {
                $ids[] = $product->getId();
                $ids[] = $product->getParentId();
            }
        }

        $slots = $page->getElementsOfType('product-box');
        /** @var CmsSlotEntity $slot */
        foreach ($slots as $slot) {
            $box = $slot->getData();

            if (!$box instanceof ProductBoxStruct) {
                continue;
            }
            if ($box->getProduct() === null) {
                continue;
            }

            $ids[] = $box->getProduct()->getId();
            $ids[] = $box->getProduct()->getParentId();
        }

        $ids = array_values(array_unique(array_filter($ids)));

        return array_merge(
            array_map([EntityCacheKeyGenerator::class, 'buildProductTag'], $ids),
            [EntityCacheKeyGenerator::buildCmsTag($page->getId())]
        );
    }
}
