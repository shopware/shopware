<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache;

use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Controller\AgenticDiscoveryController;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Forces public reverse-proxy-cacheable response headers for the agentic
 * discovery routes after Shopware's `CacheResponseSubscriber` (priority -1500)
 * has run.
 *
 * Why: the storefront cache subscriber emits `Cache-Control: no-cache, private`
 * for every storefront response that carries a session cookie. The four
 * discovery documents carry no per-buyer data, must be cacheable by reverse
 * proxies (Cloudflare, Varnish, fastly), and would otherwise be a DoS vector
 * because GPTBot/ClaudeBot/PerplexityBot all hit the same URLs thousands of
 * times per day.
 *
 * The controller marks the request via
 * `AgenticDiscoveryController::REQUEST_ATTRIBUTE_AGENTIC_DISCOVERY`; this
 * subscriber rewrites the response headers exactly when that marker is
 * present, leaving all other storefront responses untouched.
 *
 * @experimental stableVersion:v6.8.0 feature:AGENTIC_DISCOVERY
 *
 * @internal
 */
#[Package('framework')]
class AgenticDiscoveryCacheSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            // Priority -2000 = AFTER CacheResponseSubscriber (-1500), so our
            // headers win the last-writer-wins game.
            KernelEvents::RESPONSE => ['onKernelResponse', -2000],
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $marker = $event->getRequest()->attributes->get(AgenticDiscoveryController::REQUEST_ATTRIBUTE_AGENTIC_DISCOVERY);
        if (!\is_array($marker) || !isset($marker['salesChannelId'], $marker['maxAge'], $marker['sMaxAge'])) {
            return;
        }

        $response = $event->getResponse();

        if ($response->getStatusCode() !== 200) {
            return;
        }

        // Tell Symfony's AbstractSessionListener to NOT downgrade the cache
        // policy to "no-cache, private" just because a session cookie is
        // attached. Required because these are public documents that must be
        // reverse-proxy cacheable.
        $response->headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, '1');

        // Drop everything the storefront subscribers added that would prevent
        // reverse-proxy caching, then publish our own public policy.
        $response->headers->remove('Cache-Control');
        $response->headers->remove('Pragma');
        $response->setPublic();
        $response->setMaxAge((int) $marker['maxAge']);
        $response->setSharedMaxAge((int) $marker['sMaxAge']);
        $response->headers->addCacheControlDirective('stale-while-revalidate', '60');

        // Vary on Host only — discovery content does not depend on language,
        // currency or the storefront cache hash, so any additional Vary
        // entries would fragment the reverse-proxy cache for no benefit.
        $response->headers->remove('Vary');
        $response->headers->set('Vary', 'Host');

        // Public Markdown documents must not pin a session cookie on a
        // crawler. Removing Set-Cookie also lets reverse proxies keep a
        // single shared cache entry.
        $response->headers->remove('Set-Cookie');

        // Drop the per-context cookie that Shopware's cache layer would have
        // emitted for storefront browsers.
        $response->headers->remove('sw-cache-hash');

        // Cache tag for targeted invalidation; consumed by reverse proxies
        // that honour Shopware's `sw-invalidation-states` header (Varnish
        // VCL ships in shopware/dev-ops).
        $response->headers->set(
            'sw-invalidation-states',
            \sprintf('agentic_discovery_%s', (string) $marker['salesChannelId'])
        );
    }
}
