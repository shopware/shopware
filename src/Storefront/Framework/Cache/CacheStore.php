<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache;

use Shopware\Core\Framework\Adapter\Cache\AbstractCacheTracer;
use Shopware\Core\Framework\Adapter\Cache\CacheCompressor;
use Shopware\Core\System\SalesChannel\StoreApiResponse;
use Shopware\Storefront\Framework\Cache\Event\HttpCacheHitEvent;
use Shopware\Storefront\Framework\Cache\Event\HttpCacheItemWrittenEvent;
use Shopware\Storefront\Framework\Routing\MaintenanceModeResolver;
use Shopware\Storefront\Framework\Routing\StorefrontResponse;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpCache\StoreInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class CacheStore implements StoreInterface
{
    private TagAwareAdapterInterface $cache;

    private array $locks = [];

    private CacheStateValidator $stateValidator;

    private EventDispatcherInterface $eventDispatcher;

    /**
     * @var AbstractCacheTracer<StoreApiResponse>
     */
    private AbstractCacheTracer $tracer;

    private AbstractHttpCacheKeyGenerator $cacheKeyGenerator;

    private MaintenanceModeResolver $maintenanceResolver;

    /**
     * @param AbstractCacheTracer<StoreApiResponse> $tracer
     */
    public function __construct(
        TagAwareAdapterInterface $cache,
        CacheStateValidator $stateValidator,
        EventDispatcherInterface $eventDispatcher,
        AbstractCacheTracer $tracer,
        AbstractHttpCacheKeyGenerator $cacheKeyGenerator,
        MaintenanceModeResolver $maintenanceModeResolver
    ) {
        $this->cache = $cache;
        $this->stateValidator = $stateValidator;
        $this->eventDispatcher = $eventDispatcher;
        $this->tracer = $tracer;
        $this->cacheKeyGenerator = $cacheKeyGenerator;
        $this->maintenanceResolver = $maintenanceModeResolver;
    }

    public function lookup(Request $request)
    {
        // maintenance mode active and current ip is whitelisted > disable caching
        if ($this->maintenanceResolver->isMaintenanceRequest($request)) {
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

        $this->eventDispatcher->dispatch(
            new HttpCacheHitEvent($item, $request, $response)
        );

        return $response;
    }

    public function write(Request $request, Response $response)
    {
        $key = $this->cacheKeyGenerator->generate($request);

        // maintenance mode active and current ip is whitelisted > disable caching
        if ($this->maintenanceResolver->isMaintenanceRequest($request)) {
            return $key;
        }

        if ($response instanceof StorefrontResponse) {
            $response->setData(null);
            $response->setContext(null);
        }

        $item = $this->cache->getItem($key);

        $item = CacheCompressor::compress($item, $response);
        $item->expiresAt($response->getExpires());

        $tags = $this->tracer->get('all');

        $tags = array_filter($tags, static function (string $tag): bool {
            // remove tag for global theme cache, http cache will be invalidate for each key which gets accessed in the request
            if (strpos($tag, 'theme-config') !== false) {
                return false;
            }

            // remove tag for global config cache, http cache will be invalidate for each key which gets accessed in the request
            if (strpos($tag, 'system-config') !== false) {
                return false;
            }

            return true;
        });

        $item->tag($tags);

        $this->cache->save($item);

        $this->eventDispatcher->dispatch(
            new HttpCacheItemWrittenEvent($item, $tags, $request, $response)
        );

        return $key;
    }

    public function invalidate(Request $request): void
    {
        $this->cache->deleteItem(
            $this->cacheKeyGenerator->generate($request)
        );
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
     *
     * @return bool|string true if the lock is acquired, the path to the current lock otherwise
     */
    public function lock(Request $request)
    {
        $key = $this->getLockKey($request);
        if ($this->cache->hasItem($key)) {
            return $key;
        }

        $item = $this->cache->getItem($key);
        $item->set(true);

        $this->cache->save($item);
        $this->locks[$key] = true;

        return true;
    }

    /**
     * Releases the lock for the given Request.
     *
     * @return bool False if the lock file does not exist or cannot be unlocked, true otherwise
     */
    public function unlock(Request $request)
    {
        $key = $this->getLockKey($request);

        $this->cache->deleteItem($key);

        unset($this->locks[$key]);

        return true;
    }

    /**
     * Returns whether or not a lock exists.
     *
     * @return bool true if lock exists, false otherwise
     */
    public function isLocked(Request $request)
    {
        return $this->cache->hasItem(
            $this->getLockKey($request)
        );
    }

    public function purge(string $url)
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
}
