<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache\Http;

use Shopware\Core\Framework\Event\BeforeSendResponseEvent;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;

/**
 * @internal
 */
#[Package('framework')]
readonly class CacheControlListener
{
    public function __construct(private bool $reverseProxyEnabled)
    {
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

        if ($this->isAdministrationRequest($event)) {
            return;
        }

        if (
            $this->isStoreApiRequest($event)
            && (Feature::isActive('CACHE_REWORK') || Feature::isActive('v6.8.0.0'))
        ) {
            return;
        }

        $response = $event->getResponse();

        $noStore = $response->headers->getCacheControlDirective('no-store');

        // We don't want that the client will cache the website, if no reverse proxy is configured
        $response->headers->remove('cache-control');

        if (!Feature::isActive('v6.8.0.0') && !Feature::isActive('PERFORMANCE_TWEAKS') && !Feature::isActive('CACHE_REWORK')) {
            $response->headers->remove(HttpCacheKeyGenerator::INVALIDATION_STATES_HEADER);
        }

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

        return \in_array(
            StoreApiRouteScope::ID,
            (array) $request->attributes->get(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, []),
            true
        );
    }

    private function isAdministrationRequest(BeforeSendResponseEvent $event): bool
    {
        $response = $event->getResponse();

        // Check if the response has been marked as an administration response
        if ($response->headers->get('X-Shopware-Cache-Id') === 'administration') {
            return true;
        }

        $request = $event->getRequest();

        // Check route scope attribute
        if (\in_array(
            'administration',
            (array) $request->attributes->get(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, []),
            true
        )) {
            return true;
        }

        // Fallback: Check if the route name starts with 'administration.'
        $routeName = $request->attributes->get('_route');
        if (\is_string($routeName) && \str_starts_with($routeName, 'administration.')) {
            return true;
        }

        return false;
    }
}
