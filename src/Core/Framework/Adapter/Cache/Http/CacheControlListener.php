<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache\Http;

use Shopware\Core\Framework\Adapter\Cache\Http\Event\BeforeCacheControlEvent;
use Shopware\Core\Framework\Event\BeforeSendResponseEvent;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 *
 * @deprecated tag:v6.8.0 - Will be removed without replacement
 */
#[Package('framework')]
readonly class CacheControlListener
{
    public function __construct(
        private bool $reverseProxyEnabled,
        private EventDispatcherInterface $eventDispatcher
    ) {
    }

    /**
     * In the default HttpCache implementation the reverse proxy cache is implemented too in PHP and triggered before the response is send to the client. We don't need to send the "real" cache-control headers to the end client (browser/cloudflare).
     * If a external reverse proxy cache is used we still need to provide the actual cache-control, so the external system can cache the system correctly and set the cache-control again to
     */
    public function __invoke(BeforeSendResponseEvent $event): void
    {
        if ($this->reverseProxyEnabled) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();

        // Dispatch event to allow listeners to skip cache control modification
        $cacheControlEvent = new BeforeCacheControlEvent($request, $response);
        $this->eventDispatcher->dispatch($cacheControlEvent);

        if ($cacheControlEvent->shouldSkipCacheControl()) {
            return;
        }

        if (
            ($this->isStoreApiRequest($event) || $this->isStorefrontRequest($event))
            && (Feature::isActive('CACHE_REWORK') || Feature::isActive('v6.8.0.0'))
        ) {
            return;
        }

        $noStore = $response->headers->getCacheControlDirective('no-store');

        // We don't want that the client will cache the website, if no reverse proxy is configured
        $response->headers->remove('cache-control');

        $response->headers->remove(HttpCacheKeyGenerator::INVALIDATION_STATES_HEADER);

        $response->setPrivate();

        if ($noStore) {
            $response->headers->addCacheControlDirective('no-store');
        } else {
            $response->headers->addCacheControlDirective('no-cache');
        }
    }

    private function isStoreApiRequest(BeforeSendResponseEvent $event): bool
    {
        $request = $event->getRequest();
        $routeScope = $request->attributes->get(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, []);

        return \in_array(StoreApiRouteScope::ID, $routeScope, true);
    }

    private function isStorefrontRequest(BeforeSendResponseEvent $event): bool
    {
        $request = $event->getRequest();
        $routeScope = $request->attributes->get(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, []);

        return \in_array('storefront', $routeScope, true);
    }
}
