<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerLogoutEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\Event\SalesChannelContextResolvedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Drives the session held context token through the request lifecycle, for the storefront (owner)
 * and for Store API borrowers alike - see SessionContextTokenAccessor for the two roles.
 *
 * Rotations are followed through the domain events that cause them instead of by diffing tokens at
 * response time: login and registration (CustomerLoginEvent), logout (CustomerLogoutEvent, whose
 * context already carries the fresh token the logout route built) and an expired token swapped by
 * the context service (SalesChannelContextResolvedEvent). One mechanism for both surfaces.
 *
 * @internal
 */
#[Package('framework')]
class SessionContextTokenSubscriber implements EventSubscriberInterface
{
    use RouteScopeCheckTrait;

    /**
     * Before routing (RouterListener runs at 32): the owner is recognized by a request attribute the
     * storefront request transformer sets, and the rest of the request pipeline expects the token
     * header to be in place from here on.
     */
    private const PRIORITY_START = 40;

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
        private readonly RequestStack $requestStack,
        private readonly RouteScopeRegistry $routeScopeRegistry
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [
                ['startSession', self::PRIORITY_START],
            ],
            KernelEvents::RESPONSE => [
                ['enforceCacheControl', self::PRIORITY_CACHE_CONTROL],
            ],
            CustomerLoginEvent::class => 'onCustomerLogin',
            CustomerLogoutEvent::class => 'onCustomerLogout',
            SalesChannelContextResolvedEvent::class => 'onContextResolved',
        ];
    }

    public function startSession(RequestEvent $event): void
    {
        $mainRequest = $this->requestStack->getMainRequest();

        if ($mainRequest === null) {
            return;
        }

        $this->sessionContextToken->startForOwner($mainRequest, $event->getRequest());
    }

    public function onCustomerLogin(CustomerLoginEvent $event): void
    {
        $this->rotate($event->getSalesChannelId(), $event->getContextToken());
    }

    public function onCustomerLogout(CustomerLogoutEvent $event): void
    {
        // Logging out ends the session in every sense: the old session is destroyed, not just left
        // behind under a stale ID.
        $this->rotate($event->getSalesChannelId(), $event->getSalesChannelContext()->getToken(), true);
    }

    public function onContextResolved(SalesChannelContextResolvedEvent $event): void
    {
        $context = $event->getSalesChannelContext();

        if ($event->getUsedToken() === $context->getToken()) {
            return;
        }

        $this->rotate($context->getSalesChannelId(), $context->getToken());
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

    private function rotate(string $salesChannelId, string $token, bool $destroyOldSession = false): void
    {
        $mainRequest = $this->requestStack->getMainRequest();

        if ($mainRequest === null) {
            return;
        }

        $this->sessionContextToken->rotate($mainRequest, $salesChannelId, $token, $destroyOldSession);
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
