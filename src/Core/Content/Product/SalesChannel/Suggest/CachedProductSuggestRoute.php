<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Suggest;

use Shopware\Core\Content\Product\Events\ProductSuggestRouteCacheKeyEvent;
use Shopware\Core\Content\Product\Events\ProductSuggestRouteCacheTagsEvent;
use Shopware\Core\Framework\Adapter\Cache\AbstractCacheTracer;
use Shopware\Core\Framework\Adapter\Cache\CacheValueCompressor;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RuleAreas;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Json;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\StoreApiResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(defaults: ['_routeScope' => ['store-api']])]
#[Package('system-settings')]
class CachedProductSuggestRoute extends AbstractProductSuggestRoute
{
    private const NAME = 'product-suggest-route';

    /**
     * @internal
     *
     * @param AbstractCacheTracer<ProductSuggestRouteResponse> $tracer
     * @param array<string> $states
     */
    public function __construct(
        private readonly AbstractProductSuggestRoute $decorated,
        private readonly CacheInterface $cache,
        private readonly EntityCacheKeyGenerator $generator,
        private readonly AbstractCacheTracer $tracer,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly array $states
    ) {
    }

    public function getDecorated(): AbstractProductSuggestRoute
    {
        return $this->decorated;
    }

    #[Route(path: '/store-api/search-suggest', name: 'store-api.search.suggest', methods: ['POST'], defaults: ['_entity' => 'product'])]
    public function load(Request $request, SalesChannelContext $context, Criteria $criteria): ProductSuggestRouteResponse
    {
        if ($context->hasState(...$this->states)) {
            return $this->getDecorated()->load($request, $context, $criteria);
        }

        $key = $this->generateKey($request, $context, $criteria);

        if ($key === null) {
            return $this->getDecorated()->load($request, $context, $criteria);
        }

        $value = $this->cache->get($key, function (ItemInterface $item) use ($request, $context, $criteria) {
            $response = $this->tracer->trace(self::NAME, fn () => $this->getDecorated()->load($request, $context, $criteria));

            $item->tag($this->generateTags($request, $response, $context, $criteria));

            return CacheValueCompressor::compress($response);
        });

        return CacheValueCompressor::uncompress($value);
    }

    private function generateKey(Request $request, SalesChannelContext $context, Criteria $criteria): ?string
    {
        $parts = [
            $this->generator->getCriteriaHash($criteria),
            $this->generator->getSalesChannelContextHash($context, [RuleAreas::PRODUCT_AREA]),
            $request->get('search'),
        ];

        $event = new ProductSuggestRouteCacheKeyEvent($parts, $request, $context, $criteria);
        $this->dispatcher->dispatch($event);

        if (!$event->shouldCache()) {
            return null;
        }

        return self::NAME . '-' . md5(Json::encode($event->getParts()));
    }

    /**
     * @return array<string>
     */
    private function generateTags(Request $request, StoreApiResponse $response, SalesChannelContext $context, Criteria $criteria): array
    {
        $tags = array_merge(
            $this->tracer->get(self::NAME),
            [self::NAME],
        );

        $event = new ProductSuggestRouteCacheTagsEvent($tags, $request, $response, $context, $criteria);
        $this->dispatcher->dispatch($event);

        return array_unique(array_filter($event->getTags()));
    }
}
