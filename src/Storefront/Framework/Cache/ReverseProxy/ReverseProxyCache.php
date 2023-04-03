<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache\ReverseProxy;

use Shopware\Core\Framework\Adapter\Cache\AbstractCacheTracer;
use Shopware\Core\Framework\Adapter\Cache\InvalidateCacheEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Framework\Cache\CacheResponseSubscriber;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpCache\StoreInterface;
use function array_values;

/**
 * @template TCachedContent
 */
#[Package('storefront')]
class ReverseProxyCache implements StoreInterface
{
    /**
     * @internal
     *
     * @param string[] $states
     * @param AbstractCacheTracer<TCachedContent> $tracer
     */
    public function __construct(
        private readonly AbstractReverseProxyGateway $gateway,
        private readonly AbstractCacheTracer $tracer,
        private readonly array $states
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
        $tags = $this->tracer->get('all');

        $tags = array_values(array_filter($tags, static function (string $tag): bool {
            // remove tag for global theme cache, http cache will be invalidate for each key which gets accessed in the request
            if (str_contains($tag, 'theme-config')) {
                return false;
            }

            // remove tag for global config cache, http cache will be invalidate for each key which gets accessed in the request
            if (str_contains($tag, 'system-config')) {
                return false;
            }

            return true;
        }));

        $states = $response->headers->get(CacheResponseSubscriber::INVALIDATION_STATES_HEADER, '');
        $states = array_unique(array_filter(array_merge(explode(',', $states), $this->states)));

        $response->headers->set(CacheResponseSubscriber::INVALIDATION_STATES_HEADER, \implode(',', $states));

        $this->gateway->tag(array_values($tags), $request->attributes->get(RequestTransformer::ORIGINAL_REQUEST_URI), $response);

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
     * We don't need an cleanup
     */
    public function cleanup(): void
    {
    }
}
