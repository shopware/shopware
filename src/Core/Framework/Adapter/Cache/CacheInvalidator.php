<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Adapter\Cache\InvalidatorStorage\AbstractInvalidatorStorage;
use Shopware\Core\Framework\Adapter\Cache\ReverseProxy\AbstractReverseProxyGateway;
use Shopware\Core\Framework\Adapter\Cache\ReverseProxy\ReverseProxyCache;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Backtrace\BacktraceCollector;
use Shopware\Core\PlatformRequest;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @final
 */
#[Package('framework')]
class CacheInvalidator
{
    private readonly CacheInterface $httpCacheStore;

    /**
     * @internal
     *
     * @param CacheItemPoolInterface[] $adapters
     */
    public function __construct(
        private readonly array $adapters,
        private readonly AbstractInvalidatorStorage $cache,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly LoggerInterface $logger,
        private readonly RequestStack $requestStack,
        TagAwareAdapterInterface $httpCacheStore,
        private readonly bool $softPurge,
        private readonly bool $useDelayedCache,
        private readonly bool $tagInvalidationLogEnabled,
        private readonly BacktraceCollector $backtraceCollector,
        private readonly ?AbstractReverseProxyGateway $reverseProxyGateway = null
    ) {
        $this->httpCacheStore = new Psr16Cache($httpCacheStore);
    }

    /**
     * @param array<string> $tags
     */
    public function invalidate(array $tags, bool $force = false): void
    {
        $tags = array_filter(array_unique($tags));

        if ($tags === []) {
            return;
        }

        $shouldPurge = $force || $this->shouldForceInvalidate() || !$this->useDelayedCache;

        if (!$shouldPurge) {
            try {
                $this->cache->store($tags);

                return;
            } catch (\Throwable $e) {
                $message = 'Failed to store cache invalidation tags, invalidating immediately. Error: ' . $e->getMessage();

                if (EnvironmentHelper::isCiMode()) {
                    $message = 'Failed to store cache invalidation tags (CI mode; storage may be unavailable), invalidating immediately. Error: ' . $e->getMessage();
                    $this->logger->warning($message);
                } else {
                    $this->logger->error($message);
                }
            }
        }

        $this->purge($tags);
    }

    /**
     * @return array<string>
     */
    public function invalidateExpired(): array
    {
        $tags = $this->cache->loadAndDelete();

        if ($tags === []) {
            return $tags;
        }

        $this->purge($tags);

        /**
         * when we want to invalidate the expired cache tags, we also want to invalidate the reverse proxy cache immediately
         * flush happens usually on __destruct, meaning after response was sent to the client
         *
         * @see ReverseProxyCache::__destruct
         */
        $this->reverseProxyGateway?->flush();

        return $tags;
    }

    /**
     * @param array<string> $keys
     */
    private function purge(array $keys): void
    {
        foreach ($this->adapters as $adapter) {
            $adapter->deleteItems($keys);

            if ($adapter instanceof TagAwareAdapterInterface) {
                $adapter->invalidateTags($keys);
            }
        }

        if ($this->softPurge) {
            $list = [];

            foreach ($keys as $key) {
                $list['http_invalidation_' . $key . '_timestamp'] = time();
            }

            $this->httpCacheStore->setMultiple($list);
        }

        if ($this->tagInvalidationLogEnabled) {
            $callerFrame = $this->backtraceCollector->getFirstFrame(
                fn (array $frame) => !isset($frame['class'], $frame['function'])
                    || $frame['class'] === self::class
            );

            $this->logger->info(
                \sprintf('Purged tags (%d).', \count($keys)),
                [
                    'tags' => $keys,
                    'caller' => $callerFrame?->toArray(),
                ]
            );
        }

        $this->dispatcher->dispatch(new InvalidateCacheEvent($keys));
    }

    private function shouldForceInvalidate(): bool
    {
        return $this->requestStack->getMainRequest()?->headers->get(PlatformRequest::HEADER_FORCE_CACHE_INVALIDATE) === '1';
    }
}
