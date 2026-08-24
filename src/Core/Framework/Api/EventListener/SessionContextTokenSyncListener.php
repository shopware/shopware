<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\EventListener;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RouteScopeCheckTrait;
use Shopware\Core\Framework\Routing\RouteScopeRegistry;
use Shopware\Core\Framework\Routing\SessionContextTokenAccessor;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Keeps the storefront session in sync with context token rotations caused by Store API calls, and
 * keeps the affected responses out of shared caches.
 *
 * Without the write-back the original bug stays: a same-origin single page application registers a
 * guest, the register route rotates the token (SalesChannelContextPersister::replace), and the
 * storefront session still points at the pre-rotation context - so a page refresh, the mini cart or
 * another tab show an empty cart.
 *
 * @internal
 */
#[Package('framework')]
class SessionContextTokenSyncListener implements EventSubscriberInterface
{
    use RouteScopeCheckTrait;

    /**
     * Runs after ResponseHeaderListener (0) has mirrored the rotated context token onto the
     * response, but before AbstractSessionListener (-1000): that listener only saves the session
     * and attaches the migrated session cookie to the response while the session is still open,
     * so the write-back (which regenerates the session ID) has to happen before it.
     */
    private const PRIORITY_SYNC = -500;

    /**
     * Runs after CacheResponseSubscriber::setResponseCache (-1500), which removes and rewrites
     * Cache-Control wholesale. Only a lower priority can have the final say on the header.
     */
    private const PRIORITY_CACHE_CONTROL = -1600;

    /**
     * @internal
     */
    public function __construct(
        private readonly SessionContextTokenAccessor $sessionContextToken,
        private readonly RouteScopeRegistry $routeScopeRegistry
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => [
                ['syncSession', self::PRIORITY_SYNC],
                ['enforceCacheControl', self::PRIORITY_CACHE_CONTROL],
            ],
        ];
    }

    public function syncSession(ResponseEvent $event): void
    {
        $request = $event->getRequest();

        if (!$this->isRequestScoped($request, StoreApiRouteScope::class)) {
            return;
        }

        if (!$this->sessionContextToken->isEligible($request)) {
            return;
        }

        $this->syncRotatedToken($request, $event->getResponse());
    }

    public function enforceCacheControl(ResponseEvent $event): void
    {
        $request = $event->getRequest();

        if (!$this->isRequestScoped($request, StoreApiRouteScope::class)) {
            return;
        }

        if ($request->attributes->getBoolean(SessionContextTokenAccessor::ATTRIBUTE_TOKEN_FROM_SESSION)) {
            $this->denySharedCache($event->getResponse());
        }
    }

    protected function getScopeRegistry(): RouteScopeRegistry
    {
        return $this->routeScopeRegistry;
    }

    /**
     * Follows a rotation only when this request actually operated on the context the session points
     * at - either because the token was resolved from the session, or because the client happened to
     * send that very token as a header.
     *
     * Comparing against the token the request was *resolved* with, not just against the session
     * token, is what keeps an unrelated caller honest: a request that sends a foreign token alongside
     * a session cookie (a dev harness, a second context in the same browser) must not be able to
     * repoint the shopper's storefront session at its own context.
     */
    private function syncRotatedToken(Request $request, Response $response): void
    {
        $resolvedToken = $request->attributes->get(SessionContextTokenAccessor::ATTRIBUTE_RESOLVED_TOKEN);
        $responseToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN);

        if (!\is_string($resolvedToken) || $resolvedToken === '') {
            return;
        }

        if ($responseToken === null || $responseToken === '' || $responseToken === $resolvedToken) {
            return;
        }

        $salesChannelId = (string) $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID);

        $sessionToken = $this->sessionContextToken->read($request, $salesChannelId);

        if ($sessionToken === null || $sessionToken !== $resolvedToken) {
            return;
        }

        $this->sessionContextToken->write($request, $salesChannelId, $responseToken);

        $request->attributes->set(SessionContextTokenAccessor::ATTRIBUTE_TOKEN_FROM_SESSION, true);
    }

    /**
     * The request attribute already makes CacheResponseSubscriber resolve the no-store policy, but
     * that policy carries no `private` directive. Spelling both out here is what guarantees a
     * session backed response can never be stored by a reverse proxy or CDN.
     */
    private function denySharedCache(Response $response): void
    {
        $response->headers->remove('cache-control');

        $response->setCache([
            'private' => true,
            'no_store' => true,
            'no_cache' => true,
            'must_revalidate' => true,
            'max_age' => 0,
        ]);
    }
}
