<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache\ReverseProxy;

use Shopware\Core\Framework\Adapter\Cache\AbstractCacheTracer;
use Shopware\Core\Framework\Adapter\Cache\InvalidateCacheEvent;
use Shopware\Storefront\Framework\Cache\CacheResponseSubscriber;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpCache\StoreInterface;
use function array_values;

/**
 * @template TCachedContent
 */
class ReverseProxyCache implements StoreInterface
{
    private AbstractReverseProxyGateway $gateway;

    /**
     * @var AbstractCacheTracer<TCachedContent>
     */
    private AbstractCacheTracer $tracer;

    /**
     * @var string[]
     */
    private array $states;

    /**
     * @internal
     *
     * @param string[] $states
     * @param AbstractCacheTracer<TCachedContent> $tracer
     */
    public function __construct(AbstractReverseProxyGateway $gateway, AbstractCacheTracer $tracer, array $states)
    {
        $this->gateway = $gateway;
        $this->tracer = $tracer;
        $this->states = $states;
    }

    public function __destruct()
    {
        $this->gateway->flush();
    }

    public function __invoke(InvalidateCacheEvent $event): void
    {
        $this->gateway->invalidate($event->getKeys());
    }

    /**
     * @return Response|null
     */
    public function lookup(Request $request)
    {
        return null;
    }

    public function write(Request $request, Response $response): string
    {
        $tags = $this->tracer->get('all');

        $tags = array_values(array_filter($tags, static function (string $tag): bool {
            // remove tag for global theme cache, http cache will be invalidate for each key which gets accessed in the request
            if (strpos($tag, 'theme-config') !== false) {
                return false;
            }

            // remove tag for global config cache, http cache will be invalidate for each key which gets accessed in the request
            if (strpos($tag, 'system-config') !== false) {
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
