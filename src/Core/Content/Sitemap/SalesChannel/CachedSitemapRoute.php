<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\SalesChannel;

use Shopware\Core\Content\Sitemap\Event\SitemapRouteCacheKeyEvent;
use Shopware\Core\Content\Sitemap\Event\SitemapRouteCacheTagsEvent;
use Shopware\Core\Content\Sitemap\Service\SitemapExporterInterface;
use Shopware\Core\Framework\Adapter\Cache\AbstractCacheTracer;
use Shopware\Core\Framework\Adapter\Cache\CacheValueCompressor;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Json;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(defaults: ['_routeScope' => ['store-api']])]
#[Package('sales-channel')]
class CachedSitemapRoute extends AbstractSitemapRoute
{
    final public const ALL_TAG = 'sitemap-route';

    /**
     * @internal
     *
     *  @param AbstractCacheTracer<SitemapRouteResponse> $tracer
     *  @param array<string> $states
     */
    public function __construct(
        private readonly AbstractSitemapRoute $decorated,
        private readonly CacheInterface $cache,
        private readonly EntityCacheKeyGenerator $generator,
        private readonly AbstractCacheTracer $tracer,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly array $states,
        private readonly SystemConfigService $config
    ) {
    }

    public static function buildName(string $id): string
    {
        return 'sitemap-route-' . $id;
    }

    public function getDecorated(): AbstractSitemapRoute
    {
        return $this->decorated;
    }

    #[Route(path: '/store-api/sitemap', name: 'store-api.sitemap', methods: ['GET', 'POST'])]
    public function load(Request $request, SalesChannelContext $context): SitemapRouteResponse
    {
        if ($context->hasState(...$this->states)) {
            return $this->getDecorated()->load($request, $context);
        }

        $strategy = $this->config->getInt('core.sitemap.sitemapRefreshStrategy');
        if ($strategy === SitemapExporterInterface::STRATEGY_LIVE) {
            return $this->getDecorated()->load($request, $context);
        }

        $key = $this->generateKey($request, $context);

        if ($key === null) {
            return $this->getDecorated()->load($request, $context);
        }

        $value = $this->cache->get($key, function (ItemInterface $item) use ($request, $context) {
            $name = self::buildName($context->getSalesChannelId());

            $response = $this->tracer->trace($name, fn () => $this->getDecorated()->load($request, $context));

            $item->tag($this->generateTags($response, $request, $context));

            return CacheValueCompressor::compress($response);
        });

        return CacheValueCompressor::uncompress($value);
    }

    private function generateKey(Request $request, SalesChannelContext $context): ?string
    {
        $parts = [$this->generator->getSalesChannelContextHash($context)];

        $event = new SitemapRouteCacheKeyEvent($parts, $request, $context, null);
        $this->dispatcher->dispatch($event);

        if (!$event->shouldCache()) {
            return null;
        }

        return self::buildName($context->getSalesChannelId()) . '-' . md5(Json::encode($event->getParts()));
    }

    /**
     * @return array<string>
     */
    private function generateTags(SitemapRouteResponse $response, Request $request, SalesChannelContext $context): array
    {
        $tags = array_merge(
            $this->tracer->get(self::buildName($context->getSalesChannelId())),
            [self::buildName($context->getSalesChannelId()), self::ALL_TAG]
        );

        $event = new SitemapRouteCacheTagsEvent($tags, $request, $response, $context, null);
        $this->dispatcher->dispatch($event);

        return array_unique(array_filter($event->getTags()));
    }
}
