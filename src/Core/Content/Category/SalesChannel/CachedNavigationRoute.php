<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\SalesChannel;

use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Event\NavigationRouteCacheKeyEvent;
use Shopware\Core\Content\Category\Event\NavigationRouteCacheTagsEvent;
use Shopware\Core\Framework\Adapter\Cache\AbstractCacheTracer;
use Shopware\Core\Framework\Adapter\Cache\CacheValueCompressor;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RuleAreas;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Json;
use Shopware\Core\Profiling\Profiler;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\StoreApiResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(defaults: ['_routeScope' => ['store-api']])]
#[Package('content')]
class CachedNavigationRoute extends AbstractNavigationRoute
{
    final public const ALL_TAG = 'navigation';

    final public const BASE_NAVIGATION_TAG = 'base-navigation';

    /**
     * @internal
     *
     * @param AbstractCacheTracer<NavigationRouteResponse> $tracer
     * @param array<string> $states
     */
    public function __construct(
        private readonly AbstractNavigationRoute $decorated,
        private readonly CacheInterface $cache,
        private readonly EntityCacheKeyGenerator $generator,
        private readonly AbstractCacheTracer $tracer,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly array $states
    ) {
    }

    public function getDecorated(): AbstractNavigationRoute
    {
        return $this->decorated;
    }

    #[Route(path: '/store-api/navigation/{activeId}/{rootId}', name: 'store-api.navigation', methods: ['GET', 'POST'], defaults: ['_entity' => 'category'])]
    public function load(string $activeId, string $rootId, Request $request, SalesChannelContext $context, Criteria $criteria): NavigationRouteResponse
    {
        return Profiler::trace('navigation-route', function () use ($activeId, $rootId, $request, $context, $criteria) {
            if ($context->hasState(...$this->states)) {
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
        });
    }

    public static function buildName(string $id): string
    {
        return 'navigation-route-' . $id;
    }

    /**
     * @param array<string> $tags
     */
    private function loadNavigation(Request $request, string $active, string $rootId, int $depth, SalesChannelContext $context, Criteria $criteria, array $tags = []): NavigationRouteResponse
    {
        $key = $this->generateKey($active, $rootId, $depth, $request, $context, $criteria);

        if ($key === null) {
            return $this->getDecorated()->load($active, $rootId, $request, $context, $criteria);
        }

        $value = $this->cache->get($key, function (ItemInterface $item) use ($active, $depth, $rootId, $request, $context, $criteria, $tags) {
            $request->query->set('depth', (string) $depth);

            $name = self::buildName($active);

            $response = $this->tracer->trace($name, fn () => $this->getDecorated()->load($active, $rootId, $request, $context, $criteria));

            $item->tag($this->generateTags($tags, $active, $rootId, $depth, $request, $response, $context, $criteria));

            return CacheValueCompressor::compress($response);
        });

        return CacheValueCompressor::uncompress($value);
    }

    private function isActiveLoaded(string $root, CategoryCollection $categories, string $activeId): bool
    {
        if ($root === $activeId) {
            return true;
        }

        $active = $categories->get($activeId);
        if (!$active instanceof CategoryEntity) {
            return false;
        }

        if ($active->getChildCount() === 0 && \is_string($active->getParentId())) {
            return $categories->has($active->getParentId());
        }

        foreach ($categories as $category) {
            if ($category->getParentId() === $activeId) {
                return true;
            }
        }

        return false;
    }

    private function generateKey(string $active, string $rootId, int $depth, Request $request, SalesChannelContext $context, Criteria $criteria): ?string
    {
        $parts = [
            $rootId,
            $depth,
            $this->generator->getCriteriaHash($criteria),
            $this->generator->getSalesChannelContextHash($context, [RuleAreas::CATEGORY_AREA]),
        ];

        $event = new NavigationRouteCacheKeyEvent($parts, $active, $rootId, $depth, $request, $context, $criteria);
        $this->dispatcher->dispatch($event);

        if (!$event->shouldCache()) {
            return null;
        }

        return self::buildName($active) . '-' . md5(Json::encode($event->getParts()));
    }

    /**
     * @param array<string> $tags
     *
     * @return array<string>
     */
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
