<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\DeviceBoundSession;

use Shopware\Core\Checkout\Customer\Event\CustomerLogoutEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\KernelListenerPriorities;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Routing\StorefrontRouteScope;
use Shopware\Storefront\Framework\Routing\StorefrontSubscriber;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

/**
 * Enforces the device bound cookie on storefront requests of bound sessions,
 * and offers registration to browsers of logged-in customers.
 *
 * @internal
 */
#[Package('framework')]
class DeviceBoundSessionSubscriber implements EventSubscriberInterface
{
    public const ROUTE_PREFIX = 'frontend.device_bound_session.';

    /**
     * `null` when the gate did not run, `false` when the context token is not bound
     */
    private const ATTRIBUTE_SESSION = '_device_bound_session';

    public function __construct(
        private readonly bool $enabled,
        private readonly DeviceBoundSessionService $service,
        private readonly StorefrontSubscriber $storefrontSubscriber,
        private readonly RouterInterface $router,
        private readonly RequestStack $requestStack,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // the context has to be resolved with the token the gate left in place
            KernelEvents::CONTROLLER => ['enforceBinding', KernelListenerPriorities::KERNEL_CONTROLLER_EVENT_CONTEXT_RESOLVE_PRE],
            StorefrontRouteScope::ID . '.scope.response' => 'offerRegistration',
            CustomerLogoutEvent::class => 'removeBinding',
        ];
    }

    public function enforceBinding(ControllerEvent $event): void
    {
        $request = $event->getRequest();
        if (!$this->enabled || !$event->isMainRequest() || !$this->isGuardedRoute($request)) {
            return;
        }

        $contextToken = $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN);
        if ($contextToken === null || $contextToken === '') {
            return;
        }

        $session = $this->service->findByContextToken($contextToken);
        if ($session === null) {
            $request->attributes->set(self::ATTRIBUTE_SESSION, false);

            return;
        }

        $cookieValue = $request->cookies->get($this->service->getCookieName($session));
        if ($this->service->isCookieValid($session, \is_string($cookieValue) ? $cookieValue : null)) {
            $request->attributes->set(self::ATTRIBUTE_SESSION, $session);

            return;
        }

        // The session cookie was presented without proof of the device key, so it is treated as stolen:
        // the binding and the storefront session are revoked, which logs out every copy of the cookie.
        $this->service->delete($session);
        $this->storefrontSubscriber->updateSession(Random::getAlphanumericString(32), true);
        $request->attributes->set(self::ATTRIBUTE_SESSION, false);
    }

    public function offerRegistration(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();

        if (!$this->enabled || !$event->isMainRequest() || $response->getStatusCode() >= 400) {
            return;
        }

        // an unbound session was confirmed during this request, so the header is only sent on uncached responses
        if ($request->attributes->get(self::ATTRIBUTE_SESSION) !== false) {
            return;
        }

        // `_httpCache` is not always a bool, so it must not be read via `getBoolean()`
        if ($request->attributes->get(PlatformRequest::ATTRIBUTE_HTTP_CACHE) || $response->headers->hasCacheControlDirective('public')) {
            return;
        }

        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);
        if (!$context instanceof SalesChannelContext || $context->getCustomer() === null) {
            return;
        }

        $contextToken = $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN);
        if ($contextToken !== $context->getToken()) {
            return;
        }

        $response->headers->set(DeviceBoundSessionHeader::REGISTRATION, \sprintf(
            '(%s);path=%s;challenge=%s',
            DeviceBoundSessionProofVerifier::ALGORITHM,
            DeviceBoundSessionHeader::serializeString($this->router->generate(self::ROUTE_PREFIX . 'register')),
            DeviceBoundSessionHeader::serializeString($this->service->createRegistrationChallenge($contextToken)),
        ));
    }

    /**
     * The logout event already carries the replacement context, so the binding verified by the gate is removed.
     */
    public function removeBinding(): void
    {
        $session = $this->requestStack->getMainRequest()?->attributes->get(self::ATTRIBUTE_SESSION);
        if ($session instanceof DeviceBoundSession) {
            $this->service->delete($session);
        }
    }

    private function isGuardedRoute(Request $request): bool
    {
        if (!\in_array(StorefrontRouteScope::ID, $request->attributes->get(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, []), true)) {
            return false;
        }

        // the refresh request arrives without the bound cookie, that is why it is sent
        return !str_starts_with((string) $request->attributes->get('_route'), self::ROUTE_PREFIX);
    }
}
