<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\SalesChannel;

use OpenApi\Annotations as OA;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\Event\NavigationRouteCacheKeyEvent;
use Shopware\Core\Content\Category\Event\NavigationRouteCacheTagsEvent;
use Shopware\Core\Framework\Adapter\Cache\AbstractCacheTracer;
use Shopware\Core\Framework\Adapter\Cache\CacheCompressor;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\JsonFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\Annotation\Entity;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\StoreApiResponse;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @RouteScope(scopes={"store-api"})
 */
class CachedNavigationRoute extends AbstractNavigationRoute
{
    public const ALL_TAG = 'navigation';

    public const BASE_NAVIGATION_TAG = 'base-navigation';

    private AbstractNavigationRoute $decorated;

    private TagAwareAdapterInterface $cache;

    private EntityCacheKeyGenerator $generator;

    /**
     * @var AbstractCacheTracer<NavigationRouteResponse>
     */
    private AbstractCacheTracer $tracer;

    private array $states;

    private EventDispatcherInterface $dispatcher;

    private LoggerInterface $logger;

    /**
     * @param AbstractCacheTracer<NavigationRouteResponse> $tracer
     */
    public function __construct(
        AbstractNavigationRoute $decorated,
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

    public function getDecorated(): AbstractNavigationRoute
    {
        return $this->decorated;
    }

    /**
     * @Since("6.2.0.0")
     * @Entity("category")
     * @OA\Post(
     *      path="/navigation/{requestActiveId}/{requestRootId}",
     *      summary="Fetch a navigation menu",
     *      description="This endpoint returns categories that can be used as a page navigation. You can either return them as a tree or as a flat list. You can also control the depth of the tree.

Instead of passing uuids, you can also use one of the following aliases for the activeId and rootId parameters to get the respective navigations of your sales channel.

* main-navigation
* service-navigation
* footer-navigation",
     *      operationId="readNavigation",
     *      tags={"Store API", "Category"},
     *      @OA\Parameter(name="Api-Basic-Parameters"),
     *      @OA\Parameter(
     *          name="sw-include-seo-urls",
     *          description="Instructs Shopware to try and resolve SEO URLs for the given navigation item",
     *          @OA\Schema(type="boolean"),
     *          in="header",
     *          required=false
     *      ),
     *      @OA\Parameter(
     *          name="requestActiveId",
     *          description="Identifier of the active category in the navigation tree (if not used, just set to the same as rootId).",
     *          @OA\Schema(type="string", pattern="^[0-9a-f]{32}$"),
     *          in="path",
     *          required=true
     *      ),
     *      @OA\Parameter(
     *          name="requestRootId",
     *          description="Identifier of the root category for your desired navigation tree. You can use it to fetch sub-trees of your navigation tree.",
     *          @OA\Schema(type="string", pattern="^[0-9a-f]{32}$"),
     *          in="path",
     *          required=true
     *      ),
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              @OA\Property(
     *                  property="depth",
     *                  description="Determines the depth of fetched navigation levels.",
     *                  @OA\Schema(type="integer", default="2")
     *              ),
     *              @OA\Property(
     *                  property="buildTree",
     *                  description="Return the categories as a tree or as a flat list.",
     *                  @OA\Schema(type="boolean", default="true")
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response="200",
     *          description="All available navigations",
     *          @OA\JsonContent(ref="#/components/schemas/NavigationRouteResponse")
     *     )
     * )
     * @Route("/store-api/navigation/{activeId}/{rootId}", name="store-api.navigation", methods={"GET", "POST"})
     */
    public function load(string $activeId, string $rootId, Request $request, SalesChannelContext $context, Criteria $criteria): NavigationRouteResponse
    {
        if ($context->hasState(...$this->states)) {
            $this->logger->info('cache-miss: ' . self::buildName($activeId));

            return $this->getDecorated()->load($activeId, $rootId, $request, $context, $criteria);
        }

        $depth = $request->query->getInt('depth', $request->request->getInt('depth', 2));

        // first we load the base navigation, the base navigation is shared for all storefront listings
        $response = $this->loadNavigation($request, $rootId, $rootId, $depth, $context, $criteria, [self::ALL_TAG, self::BASE_NAVIGATION_TAG]);

        // no we have to check if the active category is loaded and the children of the active category are loaded
        if ($this->isActiveLoaded($rootId, $response->getCategories(), $activeId)) {
            return $response;
        }

        // reload missing children of active category, depth 0 allows us the skip base navigation loading in the core route
        $active = $this->loadNavigation($request, $activeId, $rootId, 0, $context, $criteria, [self::ALL_TAG]);

        $response->getCategories()->merge($active->getCategories());

        return $response;
    }

    public static function buildName(string $id): string
    {
        return 'navigation-route-' . $id;
    }

    private function loadNavigation(Request $request, string $active, string $rootId, int $depth, SalesChannelContext $context, Criteria $criteria, array $tags = []): NavigationRouteResponse
    {
        $item = $this->cache->getItem(
            $this->generateKey($active, $rootId, $depth, $request, $context, $criteria)
        );

        try {
            if ($item->isHit() && $item->get()) {
                $this->logger->info('cache-hit: ' . self::buildName($active));

                return CacheCompressor::uncompress($item);
            }
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage());
        }

        $this->logger->info('cache-miss: ' . self::buildName($active));

        $request->query->set('depth', (string) $depth);

        $name = self::buildName($active);

        $response = $this->tracer->trace($name, function () use ($active, $rootId, $request, $context, $criteria) {
            return $this->getDecorated()->load($active, $rootId, $request, $context, $criteria);
        });

        $item = CacheCompressor::compress($item, $response);

        $item->tag($this->generateTags($tags, $active, $rootId, $depth, $request, $response, $context, $criteria));

        $this->cache->save($item);

        return $response;
    }

    private function isActiveLoaded(string $root, CategoryCollection $categories, string $activeId): bool
    {
        if ($root === $activeId) {
            return true;
        }

        $active = $categories->get($activeId);
        if ($active === null) {
            return false;
        }

        if ($active->getChildCount() === 0) {
            return $categories->has($active->getParentId());
        }

        foreach ($categories as $category) {
            if ($category->getParentId() === $activeId) {
                return true;
            }
        }

        return false;
    }

    private function generateKey(string $active, string $rootId, int $depth, Request $request, SalesChannelContext $context, Criteria $criteria): string
    {
        $parts = [
            self::buildName($active),
            $rootId,
            $depth,
            $this->generator->getCriteriaHash($criteria),
            $this->generator->getSalesChannelContextHash($context),
        ];

        $event = new NavigationRouteCacheKeyEvent($parts, $active, $rootId, $depth, $request, $context, $criteria);
        $this->dispatcher->dispatch($event);

        return md5(JsonFieldSerializer::encodeJson($event->getParts()));
    }

    private function generateTags(array $tags, string $active, string $rootId, int $depth, Request $request, StoreApiResponse $response, SalesChannelContext $context, Criteria $criteria): array
    {
        $tags = array_merge(
            $tags,
            $this->tracer->get(self::buildName($context->getSalesChannelId())),
            [self::buildName($context->getSalesChannelId())]
        );

        $event = new NavigationRouteCacheTagsEvent($tags, $active, $rootId, $depth, $request, $response, $context, $criteria);
        $this->dispatcher->dispatch($event);

        return array_unique(array_filter($event->getTags()));
    }
}
