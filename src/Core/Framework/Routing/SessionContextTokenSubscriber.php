<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerLogoutEvent;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\Event\SalesChannelContextResolvedEvent;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
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
     * After every listener that may put the context token onto the response, notably the Core
     * ResponseHeaderListener (0) and the routes setting it themselves.
     */
    private const PRIORITY_STRIP_TOKEN = -1600;

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
            KernelEvents::CONTROLLER => [
                ['resolveFromSession', KernelListenerPriorities::KERNEL_CONTROLLER_EVENT_CONTEXT_RESOLVE_PRE],
            ],
            KernelEvents::RESPONSE => [
                ['stripContextToken', self::PRIORITY_STRIP_TOKEN],
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

        $this->sessionContextToken->start($mainRequest, $event->getRequest());
    }

    /**
     * Runs after SalesChannelAuthenticationListener (-2) established the sales channel and before
     * context resolution (-10).
     */
    public function resolveFromSession(ControllerEvent $event): void
    {
        $request = $event->getRequest();

        if (!$this->isRequestScoped($request, StoreApiRouteScope::class)) {
            return;
        }

        $token = $this->sessionContextToken->read(
            $request,
            (string) $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID)
        );

        if ($token === null) {
            return;
        }

        $request->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $token);
        $request->attributes->set(SessionContextTokenAccessor::ATTRIBUTE_TOKEN_FROM_SESSION, true);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_NO_STORE, true);
    }

    public function onCustomerLogin(CustomerLoginEvent $event): void
    {
        $this->rotate($event->getSalesChannelId(), $event->getContextToken());
    }

    public function onCustomerLogout(CustomerLogoutEvent $event): void
    {
        // the logout route already rotated the context and returns that token in its body
        $token = Feature::isActive('v6.8.0.0')
            ? $event->getSalesChannelContext()->getToken()
            : Random::getAlphanumericString(32);

        $this->rotate($event->getSalesChannelId(), $token, true);
    }

    public function onContextResolved(SalesChannelContextResolvedEvent $event): void
    {
        $context = $event->getSalesChannelContext();

        if ($event->getUsedToken() === $context->getToken()) {
            return;
        }

        $this->rotate($context->getSalesChannelId(), $context->getToken());
    }

    /**
     * Session-sourced clients do not need a response token header. Existing response-body token
     * fields remain unchanged, so this does not make the token inaccessible to same-origin scripts.
     */
    public function stripContextToken(ResponseEvent $event): void
    {
        $request = $event->getRequest();

        if (!$this->isRequestScoped($request, StoreApiRouteScope::class)) {
            return;
        }

        if (!$request->attributes->getBoolean(SessionContextTokenAccessor::ATTRIBUTE_TOKEN_FROM_SESSION)) {
            return;
        }

        $event->getResponse()->headers->remove(PlatformRequest::HEADER_CONTEXT_TOKEN);
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

        // an /api request must not migrate the storefront session
        if (!$mainRequest->attributes->get(SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST)
            && !$this->isRequestScoped($mainRequest, StoreApiRouteScope::class)
        ) {
            return;
        }

        $this->sessionContextToken->rotate($mainRequest, $salesChannelId, $token, $destroyOldSession);
    }
}
