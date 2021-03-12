<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache\ReverseProxy;

use Shopware\Core\Framework\Adapter\Cache\AbstractCacheTracer;
use Shopware\Core\Framework\Adapter\Cache\InvalidateCacheEvent;
use Shopware\Storefront\Framework\Cache\CacheTagCollection;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpCache\StoreInterface;

class ReverseProxyCache implements StoreInterface
{
    private AbstractReverseProxyGateway $gateway;

    private CacheTagCollection $cacheTagCollection;

    private AbstractCacheTracer $tracer;

    public function __construct(AbstractReverseProxyGateway $gateway, CacheTagCollection $cacheTagCollection, AbstractCacheTracer $tracer)
    {
        $this->gateway = $gateway;
        $this->cacheTagCollection = $cacheTagCollection;
        $this->tracer = $tracer;
    }

    public function __invoke(InvalidateCacheEvent $event): void
    {
        $this->gateway->invalidate($event->getKeys());
    }

    public function lookup(Request $request)
    {
        return null;
    }

    public function write(Request $request, Response $response): string
    {
        $tags = array_merge($this->cacheTagCollection->getTags(), $this->tracer->get('all'));

        $this->gateway->tag($tags, $request->attributes->get(RequestTransformer::ORIGINAL_REQUEST_URI));

        return '';
    }

    public function invalidate(Request $request): void
    {
        $uri = $request->attributes->get(RequestTransformer::ORIGINAL_REQUEST_URI);

        if ($uri === null) {
            return;
        }

        $this->gateway->ban([$uri]);
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

    /**
     * @param string $url
     */
    public function purge($url): bool
    {
        $this->gateway->ban([$url]);

        return true;
    }

    /**
     * We don't need an cleanup
     */
    public function cleanup(): void
    {
    }
}
