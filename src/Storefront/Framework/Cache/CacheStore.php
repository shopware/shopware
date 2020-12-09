<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache;

use Shopware\Core\SalesChannelRequest;
use Shopware\Storefront\Framework\Cache\Event\HttpCacheGenerateKeyEvent;
use Shopware\Storefront\Framework\Cache\Event\HttpCacheHitEvent;
use Shopware\Storefront\Framework\Cache\Event\HttpCacheItemWrittenEvent;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Shopware\Storefront\Framework\Routing\StorefrontResponse;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpCache\StoreInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class CacheStore implements StoreInterface
{
    /**
     * @var TagAwareAdapterInterface
     */
    private $cache;

    /**
     * @var array
     */
    private $locks = [];

    /**
     * @var CacheStateValidator
     */
    private $stateValidator;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var string
     */
    private $cacheHash;

    /**
     * @var CacheTagCollection
     */
    private $cacheTagCollection;

    public function __construct(
        string $cacheHash,
        TagAwareAdapterInterface $cache,
        CacheStateValidator $stateValidator,
        EventDispatcherInterface $eventDispatcher,
        CacheTagCollection $cacheTagCollection
    ) {
        $this->cache = $cache;
        $this->stateValidator = $stateValidator;
        $this->eventDispatcher = $eventDispatcher;
        $this->cacheHash = $cacheHash;
        $this->cacheTagCollection = $cacheTagCollection;
    }

    public function lookup(Request $request)
    {
        $key = $this->generateCacheKey($request);

        $item = $this->cache->getItem($key);

        if (!$item->isHit() || !$item->get()) {
            return null;
        }

        /** @var Response $response */
        $response = unserialize($item->get());

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
        $key = $this->generateCacheKey($request);
        if ($response instanceof StorefrontResponse) {
            $response->setData(null);
            $response->setContext(null);
        }

        $item = $this->cache->getItem($key);
        $item->set(serialize($response));
        $item->expiresAt($response->getExpires());

        $tags = $this->cacheTagCollection->getTags();

        if (!empty($tags) && $item instanceof CacheItem) {
            $item->tag($tags);
        }

        $this->cache->save($item);

        $this->eventDispatcher->dispatch(
            new HttpCacheItemWrittenEvent($item, $tags, $request, $response)
        );

        return $key;
    }

    public function invalidate(Request $request): void
    {
        $this->cache->deleteItem(
            $this->generateCacheKey($request)
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

    public function purge($url)
    {
        $http = preg_replace('#^https:#', 'http:', $url);
        $https = preg_replace('#^http:#', 'https:', $url);

        $httpPurged = $this->unlock(Request::create($http));
        $httpsPurged = $this->unlock(Request::create($https));

        return $httpPurged || $httpsPurged;
    }

    private function getLockKey(Request $request): string
    {
        return 'http_lock_' . $this->generateCacheKey($request);
    }

    /**
     * Generates a cache key for the given Request.
     *
     * This method should return a key that must only depend on a
     * normalized version of the request URI.
     *
     * If the same URI can have more than one representation, based on some
     * headers, use a Vary header to indicate them, and each representation will
     * be stored independently under the same cache key.
     *
     * @return string A key for the given Request
     */
    private function generateCacheKey(Request $request): string
    {
        $uri = $this->getRequestUri($request) . $this->cacheHash;

        $event = new HttpCacheGenerateKeyEvent($request, 'md' . hash('sha256', $uri));

        $this->eventDispatcher->dispatch($event);

        $hash = $event->getHash();

        if ($request->cookies->has(CacheResponseSubscriber::CONTEXT_CACHE_COOKIE)) {
            return hash('sha256', $hash . '-' . $request->cookies->get(CacheResponseSubscriber::CONTEXT_CACHE_COOKIE));
        }

        if ($request->cookies->has(CacheResponseSubscriber::CURRENCY_COOKIE)) {
            return hash('sha256', $hash . '-' . $request->cookies->get(CacheResponseSubscriber::CURRENCY_COOKIE));
        }

        if ($request->attributes->has(SalesChannelRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID)) {
            return hash('sha256', $hash . '-' . $request->attributes->get(SalesChannelRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID));
        }

        return $hash;
    }

    private function getRequestUri(Request $request): string
    {
        $params = $request->query->all();
        if (\count($params) === 0) {
            return sprintf(
                '%s%s%s',
                $request->getSchemeAndHttpHost(),
                $request->attributes->get(RequestTransformer::SALES_CHANNEL_BASE_URL),
                $request->getPathInfo()
            );
        }

        $params = $request->query->all();
        ksort($params);
        $params = http_build_query($params);

        return sprintf(
            '%s%s%s%s',
            $request->getSchemeAndHttpHost(),
            $request->attributes->get(RequestTransformer::SALES_CHANNEL_BASE_URL),
            $request->getPathInfo(),
            '?' . $params
        );
    }
}
