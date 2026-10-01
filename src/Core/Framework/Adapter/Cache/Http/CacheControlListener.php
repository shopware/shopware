<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache\Http;

use Shopware\Core\Framework\Adapter\Cache\Http\Event\BeforeCacheControlEvent;
use Shopware\Core\Framework\Event\BeforeSendResponseEvent;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 *
 * @deprecated tag:v6.8.0 - reason:remove-subscriber - Will be removed without replacement
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

        // With the cache rework the cache-control headers should be delivered to the user,
        // so this listener must not touch them anymore. It is removed with 6.8.0.
        if (Feature::isActive('CACHE_REWORK') || Feature::isActive('v6.8.0.0')) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();

        // @deprecated tag:v6.8.0 - Dispatch event to allow listeners to skip cache control modification.
        $skipCacheControl = Feature::silent('v6.8.0.0', function () use ($request, $response): bool {
            $cacheControlEvent = new BeforeCacheControlEvent($request, $response);
            $this->eventDispatcher->dispatch($cacheControlEvent);

            return $cacheControlEvent->shouldSkipCacheControl();
        });

        if ($skipCacheControl) {
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
}
