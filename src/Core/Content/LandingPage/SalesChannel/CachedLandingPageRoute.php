<?php declare(strict_types=1);

namespace Shopware\Core\Content\LandingPage\SalesChannel;

use OpenApi\Annotations as OA;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ProductBoxStruct;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ProductSliderStruct;
use Shopware\Core\Content\LandingPage\Event\LandingPageRouteCacheKeyEvent;
use Shopware\Core\Content\LandingPage\Event\LandingPageRouteCacheTagsEvent;
use Shopware\Core\Framework\Adapter\Cache\AbstractCacheTracer;
use Shopware\Core\Framework\Adapter\Cache\CacheCompressor;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\JsonFieldSerializer;
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
class CachedLandingPageRoute extends AbstractLandingPageRoute
{
    private AbstractLandingPageRoute $decorated;

    private TagAwareAdapterInterface $cache;

    private EntityCacheKeyGenerator $generator;

    /**
     * @var AbstractCacheTracer<LandingPageRouteResponse>
     */
    private AbstractCacheTracer $tracer;

    private array $states;

    private EventDispatcherInterface $dispatcher;

    private LoggerInterface $logger;

    /**
     * @param AbstractCacheTracer<LandingPageRouteResponse> $tracer
     */
    public function __construct(
        AbstractLandingPageRoute $decorated,
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
            $this->logger->info('cache-miss: ' . self::buildName($landingPageId));

            return $this->getDecorated()->load($landingPageId, $request, $context);
        }

        $item = $this->cache->getItem(
            $this->generateKey($landingPageId, $request, $context)
        );

        try {
            if ($item->isHit() && $item->get()) {
                $this->logger->info('cache-hit: ' . self::buildName($landingPageId));

                return CacheCompressor::uncompress($item);
            }
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage());
        }

        $this->logger->info('cache-miss: ' . self::buildName($landingPageId));

        $name = self::buildName($landingPageId);
        $response = $this->tracer->trace($name, function () use ($landingPageId, $request, $context) {
            return $this->getDecorated()->load($landingPageId, $request, $context);
        });

        $item = CacheCompressor::compress($item, $response);

        $item->tag($this->generateTags($landingPageId, $response, $request, $context));

        $this->cache->save($item);

        return $response;
    }

    private function generateKey(string $landingPageId, Request $request, SalesChannelContext $context): string
    {
        $parts = array_merge(
            $request->query->all(),
            $request->request->all(),
            [
                self::buildName($landingPageId),
                $this->generator->getSalesChannelContextHash($context),
            ]
        );

        $event = new LandingPageRouteCacheKeyEvent($landingPageId, $parts, $request, $context, null);
        $this->dispatcher->dispatch($event);

        return md5(JsonFieldSerializer::encodeJson($event->getParts()));
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
