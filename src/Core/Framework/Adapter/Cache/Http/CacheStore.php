<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache\Http;

use Shopware\Core\Framework\Adapter\Cache\AbstractCacheTracer;
use Shopware\Core\Framework\Adapter\Cache\CacheCompressor;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\Adapter\Cache\Event\HttpCacheHitEvent;
use Shopware\Core\Framework\Adapter\Cache\Event\HttpCacheStoreEvent;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\MaintenanceModeResolver;
use Shopware\Core\System\SalesChannel\StoreApiResponse;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpCache\StoreInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('core')]
class CacheStore implements StoreInterface
{
    final public const TAG_HEADER = 'sw-cache-tags';

    /**
     * @var array<string, bool>
     */
    private array $locks = [];

    private readonly string $sessionName;

    /**
     * @internal
     *
     * @param AbstractCacheTracer<StoreApiResponse> $tracer
     * @param array<string, mixed> $sessionOptions
     */
    public function __construct(
        private readonly TagAwareAdapterInterface $cache,
        private readonly CacheStateValidator $stateValidator,
        private readonly EventDispatcherInterface $eventDispatcher,
        // @deprecated tag:v6.7.0 - remove
        private readonly AbstractCacheTracer $tracer,
        private readonly HttpCacheKeyGenerator $cacheKeyGenerator,
        private readonly MaintenanceModeResolver $maintenanceResolver,
        array $sessionOptions,
        private readonly CacheTagCollector $collector
    ) {
        $this->sessionName = $sessionOptions['name'] ?? 'session-';
    }

    public function lookup(Request $request): ?Response
    {
        // maintenance mode active and current ip is whitelisted > disable caching
        if (!$this->maintenanceResolver->shouldBeCached($request)) {
            return null;
        }

        $key = $this->cacheKeyGenerator->generate($request);

        $item = $this->cache->getItem($key);

        if (!$item->isHit() || !$item->get()) {
            return null;
        }

        /** @var Response $response */
        $response = CacheCompressor::uncompress($item);

        if (!$this->stateValidator->isValid($request, $response)) {
            return null;
        }

        $event = new HttpCacheHitEvent($item, $request, $response);

        $this->eventDispatcher->dispatch($event);

        return $response;
    }

    public function write(Request $request, Response $response): string
    {
        $key = $this->cacheKeyGenerator->generate($request);

        // maintenance mode active and current ip is whitelisted > disable caching
        if ($this->maintenanceResolver->isMaintenanceRequest($request)) {
            return $key;
        }

        $tags = $this->getTags($request);

        if ($response->headers->has(self::TAG_HEADER)) {
            /** @var string $tagHeader */
            $tagHeader = $response->headers->get(self::TAG_HEADER);
            $responseTags = \json_decode($tagHeader, true, 512, \JSON_THROW_ON_ERROR);
            $tags = array_merge($responseTags, $tags);

            $response->headers->remove(self::TAG_HEADER);
        }

        $item = $this->cache->getItem($key);

        /**
         * Symfony pops out in AbstractSessionListener(https://github.com/symfony/symfony/blob/v5.4.5/src/Symfony/Component/HttpKernel/EventListener/AbstractSessionListener.php#L139-L186) the session and assigns it to the Response
         * We should never cache the cookie of the actual browser session, this part removes it again from the cloned response object. As they popped it out of the PHP stack, we need to from it only from the cached response
         */
        $cacheResponse = clone $response;
        $cacheResponse->headers = clone $response->headers;

        foreach ($cacheResponse->headers->getCookies() as $cookie) {
            if ($cookie->getName() === $this->sessionName) {
                $cacheResponse->headers->removeCookie($cookie->getName(), $cookie->getPath(), $cookie->getDomain());
            }
        }

        $item = CacheCompressor::compress($item, $cacheResponse);
        $item->expiresAt($cacheResponse->getExpires());
        $item->tag($tags);

        if (Feature::isActive('cache_rework')) {
            $this->eventDispatcher->dispatch(
                new HttpCacheStoreEvent($item, $tags, $request, $response)
            );

            $this->cache->save($item);
        } else {
            $this->cache->save($item);

            $this->eventDispatcher->dispatch(
                new HttpCacheStoreEvent($item, $tags, $request, $response)
            );
        }

        return $key;
    }

    public function invalidate(Request $request): void
    {
        // @see https://github.com/symfony/symfony/issues/48301
    }

    /**
     * Cleanups storage.
     */
    public function cleanup(): void
    {
        $keys = array_keys($this->locks);
        $this->cache->deleteItems($keys);
        $this->locks = [];
    }

    /**
     * Tries to lock the cache for a given Request, without blocking.
     */
    public function lock(Request $request): bool|string
    {
        $key = $this->getLockKey($request);
        if ($this->cache->hasItem($key)) {
            return $key;
        }

        $item = $this->cache->getItem($key);
        $item->set(true);
        $item->expiresAfter(3);

        $this->cache->save($item);
        $this->locks[$key] = true;

        return true;
    }

    /**
     * Releases the lock for the given Request.
     */
    public function unlock(Request $request): bool
    {
        $key = $this->getLockKey($request);

        $this->cache->deleteItem($key);

        unset($this->locks[$key]);

        return true;
    }

    /**
     * Returns whether or not a lock exists.
     */
    public function isLocked(Request $request): bool
    {
        return $this->cache->hasItem(
            $this->getLockKey($request)
        );
    }

    public function purge(string $url): bool
    {
        $http = preg_replace('#^https:#', 'http:', $url);
        if ($http === null) {
            return false;
        }

        $https = preg_replace('#^http:#', 'https:', $url);
        if ($https === null) {
            return false;
        }

        $httpPurged = $this->unlock(Request::create($http));
        $httpsPurged = $this->unlock(Request::create($https));

        return $httpPurged || $httpsPurged;
    }

    private function getLockKey(Request $request): string
    {
        return 'http_lock_' . $this->cacheKeyGenerator->generate($request);
    }

    /**
     * @return array<string>
     */
    private function getTags(Request $request): array
    {
        if (Feature::isActive('cache_rework')) {
            return $this->collector->get($request);
        }

        $tags = array_merge($this->tracer->get('all'), $this->collector->get($request));

        return array_filter($tags, static function (string $tag): bool {
            // remove tag for global theme cache, http cache will be invalidated for each key which gets accessed in the request
            if (str_contains($tag, 'theme-config')) {
                return false;
            }

            // remove tag for global config cache, http cache will be invalidated for each key which gets accessed in the request
            if (str_contains($tag, 'system-config')) {
                return false;
            }

            // remove tag for global translation cache, http cache will be invalidated for each key which gets accessed in the request
            if (str_contains($tag, 'translation.catalog.')) {
                return false;
            }

            return true;
        });
    }
}
