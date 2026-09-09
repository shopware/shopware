<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerLogoutEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\Event\SalesChannelContextResolvedEvent;
use Shopware\Core\Framework\Util\Random;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Runs the session held context token through the request lifecycle for the storefront (owner) and
 * Store API borrowers alike, see SessionContextTokenAccessor. Rotations are followed through the
 * events that cause them.
 *
 * @internal
 */
#[Package('framework')]
class SessionContextTokenSubscriber implements EventSubscriberInterface
{
    use RouteScopeCheckTrait;

    /**
     * Before routing (RouterListener runs at 32); the owner is recognized by a request attribute.
     */
    private const PRIORITY_START = 40;

    /**
     * After CacheResponseSubscriber::setResponseCache (-1500), which rewrites Cache-Control wholesale.
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
        $this->rotate($event->getSalesChannelId(), Random::getAlphanumericString(32), true);
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
     * The no-store policy of CacheResponseSubscriber carries no `private` directive.
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
