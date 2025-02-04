<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache\ReverseProxy;

use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\Adapter\Cache\Http\CacheStore;
use Shopware\Core\Framework\Adapter\Cache\Http\HttpCacheKeyGenerator;
use Shopware\Core\Framework\Adapter\Cache\InvalidateCacheEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpCache\StoreInterface;

/**
 * @internal
 *
 * @template TCachedContent
 */
#[Package('framework')]
class ReverseProxyCache implements StoreInterface
{
    /**
     * @internal
     *
     * @param string[] $states
     */
    public function __construct(
        private readonly AbstractReverseProxyGateway $gateway,
        private readonly array $states,
        private readonly CacheTagCollector $collector
    ) {
    }

    public function __destruct()
    {
        $this->gateway->flush();
    }

    public function __invoke(InvalidateCacheEvent $event): void
    {
        $this->gateway->invalidate($event->getKeys());
    }

    public function lookup(Request $request): ?Response
    {
        return null;
    }

    public function write(Request $request, Response $response): string
    {
        $tags = $this->collector->get($request);

        if ($response->headers->has(CacheStore::TAG_HEADER)) {
            /** @var string $tagHeader */
            $tagHeader = $response->headers->get(CacheStore::TAG_HEADER);
            $responseTags = \json_decode($tagHeader, true, 512, \JSON_THROW_ON_ERROR);
            $tags = array_merge($responseTags, $tags);

            $response->headers->remove(CacheStore::TAG_HEADER);
        }

        $states = $response->headers->get(HttpCacheKeyGenerator::INVALIDATION_STATES_HEADER, '');
        $states = array_unique(array_filter(array_merge(explode(',', $states), $this->states)));

        $response->headers->set(HttpCacheKeyGenerator::INVALIDATION_STATES_HEADER, \implode(',', $states));

        $this->gateway->tag(\array_values($tags), $request->getPathInfo(), $response);

        return '';
    }

    public function invalidate(Request $request): void
    {
        // @see https://github.com/symfony/symfony/issues/48301
    }

    /**
     * This should be done in reverse cache
     */
    public function lock(Request $request): bool
    {
        return true;
    }

    /**
     * This should be done in reverse cache
     */
    public function unlock(Request $request): bool
    {
        return true;
    }

    /**
     * This should be done in reverse cache
     */
    public function isLocked(Request $request): bool
    {
        return false;
    }

    public function purge(string $url): bool
    {
        $this->gateway->ban([$url]);

        return true;
    }

    /**
     * We don't need a cleanup
     */
    public function cleanup(): void
    {
    }
}
