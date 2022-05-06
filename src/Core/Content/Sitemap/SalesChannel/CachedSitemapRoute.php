<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Content\Sitemap\Event\SitemapRouteCacheKeyEvent;
use Shopware\Core\Content\Sitemap\Event\SitemapRouteCacheTagsEvent;
use Shopware\Core\Content\Sitemap\Service\SitemapExporterInterface;
use Shopware\Core\Framework\Adapter\Cache\AbstractCacheTracer;
use Shopware\Core\Framework\Adapter\Cache\CacheValueCompressor;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\JsonFieldSerializer;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @Route(defaults={"_routeScope"={"store-api"}})
 */
class CachedSitemapRoute extends AbstractSitemapRoute
{
    public const ALL_TAG = 'sitemap-route';

    private AbstractSitemapRoute $decorated;

    private CacheInterface $cache;

    private EntityCacheKeyGenerator $generator;

    /**
     * @var AbstractCacheTracer<SitemapRouteResponse>
     */
    private AbstractCacheTracer $tracer;

    private array $states;

    private EventDispatcherInterface $dispatcher;

    private SystemConfigService $config;

    /**
     * @internal
     *
     *  @param AbstractCacheTracer<SitemapRouteResponse> $tracer
     */
    public function __construct(
        AbstractSitemapRoute $decorated,
        CacheInterface $cache,
        EntityCacheKeyGenerator $generator,
        AbstractCacheTracer $tracer,
        EventDispatcherInterface $dispatcher,
        array $states,
        SystemConfigService $config
    ) {
        $this->decorated = $decorated;
        $this->cache = $cache;
        $this->generator = $generator;
        $this->tracer = $tracer;
        $this->states = $states;
        $this->dispatcher = $dispatcher;
        $this->config = $config;
    }

    public static function buildName(string $id): string
    {
        return 'sitemap-route-' . $id;
    }

    public function getDecorated(): AbstractSitemapRoute
    {
        return $this->decorated;
    }

    /**
     * @Since("6.3.2.0")
     * @OA\Get(
     *      path="/sitemap",
     *      summary="Fetch sitemaps",
     *      description="Fetches a list of compressed sitemap files, which are often used by search engines.",
     *      operationId="readSitemap",
     *      tags={"Store API", "Sitemap & Routes"},
     *      @OA\Response(
     *          response="200",
     *          description="Returns a list of available sitemaps.",
     *          @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Sitemap"))
     *     )
     * )
     * @Route(path="/store-api/sitemap", name="store-api.sitemap", methods={"GET", "POST"})
     */
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

        $value = $this->cache->get($key, function (ItemInterface $item) use ($request, $context) {
            $name = self::buildName($context->getSalesChannelId());

            $response = $this->tracer->trace($name, function () use ($request, $context) {
                return $this->getDecorated()->load($request, $context);
            });

            $item->tag($this->generateTags($response, $request, $context));

            return CacheValueCompressor::compress($response);
        });

        return CacheValueCompressor::uncompress($value);
    }

    private function generateKey(Request $request, SalesChannelContext $context): string
    {
        $parts = [$this->generator->getSalesChannelContextHash($context)];

        $event = new SitemapRouteCacheKeyEvent($parts, $request, $context, null);
        $this->dispatcher->dispatch($event);

        return self::buildName($context->getSalesChannelId()) . '-' . md5(JsonFieldSerializer::encodeJson($event->getParts()));
    }

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
