<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache\Http;

use Shopware\Core\Framework\Adapter\Cache\CacheCompressor;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\Adapter\Cache\Event\HttpCacheHitEvent;
use Shopware\Core\Framework\Adapter\Cache\Event\HttpCacheStoreEvent;
use Shopware\Core\Framework\Adapter\Cache\Message\RefreshHttpCacheMessage;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\MaintenanceModeResolver;
use Shopware\Core\PlatformRequest;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\HttpCache\StoreInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('framework')]
class CacheStore implements StoreInterface
{
    final public const TAG_HEADER = 'sw-cache-tags';
    private const HALF_HOUR = 1800;

    /**
     * @var array<string, bool>
     */
    private array $locks = [];

    private readonly string $sessionName;

    /**
     * @internal
     *
     * @param array<string, mixed> $sessionOptions
     */
    public function __construct(
        private readonly TagAwareAdapterInterface&CacheInterface $cache,
        private readonly CacheStateValidator $stateValidator,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly HttpCacheKeyGenerator $cacheKeyGenerator,
        private readonly MaintenanceModeResolver $maintenanceResolver,
        array $sessionOptions,
        private readonly CacheTagCollector $collector,
        private bool $softPurge,
        private readonly MessageBusInterface $bus,
    ) {
        $this->sessionName = $sessionOptions['name'] ?? PlatformRequest::FALLBACK_SESSION_NAME;
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

        /** @var Response|array{response: Response, tags: array<string>} $hitData */
        $hitData = CacheCompressor::uncompress($item);
        $tags = [];

        $response = \is_array($hitData) ? $hitData['response'] : $hitData;
        if (\is_array($hitData)) {
            $tags = $hitData['tags'] ?? [];
        }

        if ($this->softPurge) {
            $minInvalidation = $this->getMinInvalidation($tags);
            $responseGeneratedAt = new \DateTime((string) $response->headers->get('date'));
            $staleWhileRevalidate = $response->headers->getCacheControlDirective('stale-while-revalidate');

            if ($minInvalidation >= $responseGeneratedAt->getTimestamp()) {
                // The cache is too old, we need to revalidate it
                if ($staleWhileRevalidate && $responseGeneratedAt->diff(new \DateTime())->s >= (int) $staleWhileRevalidate) {
                    return null;
                }

                $lockKey = $key . '.lock';

                /**
                 * We use this cache item to lock that we dispatch only one RefreshHttpCacheMessage for the same request.
                 * This is important, because we can have multiple requests for the same page in parallel,
                 * e.g. when multiple users open the same page at the same time.
                 */
                $this->cache->get($lockKey, function (ItemInterface $item) use ($lockKey, $request): void {
                    // We keep the lock for a half hour, if not proceed in that time, the lock will be released, and we can re-dispatch the message
                    $item->expiresAfter(self::HALF_HOUR);

                    $this->bus->dispatch(new RefreshHttpCacheMessage($lockKey, $request->query->all(), $request->attributes->all(), $request->cookies->all(), $request->server->all(), Request::getTrustedProxies(), Request::getTrustedHeaderSet()));
                });
            }
        }

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

        if (!$this->stateValidator->isValid($request, $response)) {
            return $key;
        }

        $tags = $this->collector->get($request);

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

        if ($this->softPurge) {
            $item = CacheCompressor::compress($item, [
                'response' => $cacheResponse,
                'tags' => $tags,
            ]);
        } else {
            $item = CacheCompressor::compress($item, $cacheResponse);
            $item->tag($tags);
        }

        $item->expiresAt($cacheResponse->getExpires());

        $this->eventDispatcher->dispatch(
            new HttpCacheStoreEvent($item, $tags, $request, $response)
        );

        $this->cache->save($item);

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
     * Returns whether a lock exists.
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
     * @param array<string> $tags
     */
    private function getMinInvalidation(array $tags): int
    {
        $lastInvalidation = 0;

        $invalidations = $this->cache->getItems(
            array_map(
                static fn (string $tag): string => 'http_invalidation_' . $tag . '_timestamp',
                $tags
            )
        );

        foreach ($invalidations as $invalidation) {
            if ($invalidation->isHit()) {
                $timestamp = $invalidation->get();
                if ($timestamp > $lastInvalidation) {
                    $lastInvalidation = $timestamp;
                }
            }
        }

        return $lastInvalidation;
    }
}
