<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache\Http;

use Shopware\Core\Framework\Event\BeforeSendResponseEvent;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
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

        $response = $event->getResponse();

        $noStore = $response->headers->getCacheControlDirective('no-store');

        // We don't want that the client will cache the website, if no reverse proxy is configured
        $response->headers->remove('cache-control');
        $response->headers->remove(CacheResponseSubscriber::INVALIDATION_STATES_HEADER);
        $response->setPrivate();

        if ($noStore) {
            $response->headers->addCacheControlDirective('no-store');
        } else {
            $response->headers->addCacheControlDirective('no-cache');
        }
    }
}
