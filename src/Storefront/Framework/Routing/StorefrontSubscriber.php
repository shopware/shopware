<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Routing;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerLogoutEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\Event\SalesChannelContextResolvedEvent;
use Shopware\Core\Framework\Routing\Exception\CustomerNotLoggedInRoutingException;
use Shopware\Core\Framework\Routing\KernelListenerPriorities;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Event\MaintenanceRedirectEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('framework')]
class StorefrontSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly RouterInterface $router,
        private readonly MaintenanceModeResolver $maintenanceModeResolver,
        private readonly SystemConfigService $systemConfigService,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [
                ['startSession', 40],
                ['maintenanceResolver'],
            ],
            KernelEvents::EXCEPTION => [
                ['customerNotLoggedInHandler'],
                ['maintenanceResolver'],
            ],
            KernelEvents::CONTROLLER => [
                ['preventPageLoadingFromXmlHttpRequest', KernelListenerPriorities::KERNEL_CONTROLLER_EVENT_SCOPE_VALIDATE],
            ],
            CustomerLoginEvent::class => [
                'updateSessionAfterLogin',
            ],
            CustomerLogoutEvent::class => [
                'updateSessionAfterLogout',
            ],
            SalesChannelContextResolvedEvent::class => [
                ['replaceContextToken'],
            ],
        ];
    }

    public function startSession(): void
    {
        $mainRequest = $this->requestStack->getMainRequest();
        if (!$mainRequest) {
            return;
        }
        if (!$mainRequest->attributes->get(SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST)) {
            return;
        }

        if (!$mainRequest->hasSession()) {
            return;
        }

        $session = $mainRequest->getSession();

        if (!$session->isStarted()) {
            $session->start();
            $session->set('sessionId', $session->getId());
        }

        $salesChannelId = $mainRequest->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID);
        if ($salesChannelId === null) {
            $salesChannelContext = $mainRequest->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);
            if ($salesChannelContext instanceof SalesChannelContext) {
                $salesChannelId = $salesChannelContext->getSalesChannelId();
            }
        }

        // When customer binding is enabled, store tokens per sales channel to prevent
        // bound customers from being logged out when visiting other channels
        $bindingEnabled = $this->systemConfigService->getBool('core.systemWideLoginRegistration.isCustomerBoundToSalesChannel');
        $tokenKey = $bindingEnabled
            ? PlatformRequest::HEADER_CONTEXT_TOKEN . '-' . $salesChannelId
            : PlatformRequest::HEADER_CONTEXT_TOKEN;

        if ($this->shouldRenewToken($session, $salesChannelId, $tokenKey)) {
            $token = Random::getAlphanumericString(32);
            $session->set($tokenKey, $token);
            $session->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID, $salesChannelId);
        }

        $contextToken = $session->get($tokenKey);

        // Always keep the default key in sync with the current token for backward compatibility
        // This ensures code that relies on the default key (e.g., anonymous users) still works
        $session->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $contextToken);

        $mainRequest->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $contextToken);

        $currentRequest = $this->requestStack->getCurrentRequest();
        if ($currentRequest && $mainRequest !== $currentRequest) {
            $currentRequest->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $contextToken);
        }
    }

    public function updateSessionAfterLogin(CustomerLoginEvent $event): void
    {
        $token = $event->getContextToken();

        $this->updateSession($token);
    }

    public function updateSessionAfterLogout(): void
    {
        $newToken = Random::getAlphanumericString(32);

        $this->updateSession($newToken, true);
    }

    public function updateSession(string $token, bool $destroyOldSession = false): void
    {
        $mainRequest = $this->requestStack->getMainRequest();
        if (!$mainRequest) {
            return;
        }
        if (!$mainRequest->attributes->get(SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST)) {
            return;
        }

        if (!$mainRequest->hasSession()) {
            return;
        }

        $session = $mainRequest->getSession();
        $session->migrate($destroyOldSession);
        $session->set('sessionId', $session->getId());

        // When customer binding is enabled, store tokens per sales channel
        $bindingEnabled = $this->systemConfigService->getBool('core.systemWideLoginRegistration.isCustomerBoundToSalesChannel');
        if ($bindingEnabled) {
            $salesChannelId = $mainRequest->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID);
            if ($salesChannelId) {
                $tokenKey = PlatformRequest::HEADER_CONTEXT_TOKEN . '-' . $salesChannelId;
                $session->set($tokenKey, $token);
            }
        }

        // Always set the default key for backward compatibility
        $session->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $token);
        $mainRequest->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $token);
    }

    public function customerNotLoggedInHandler(ExceptionEvent $event): void
    {
        if (!$event->getRequest()->attributes->has(SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST)) {
            return;
        }

        if (!$this->shouldRedirectLoginPage($event->getThrowable())) {
            return;
        }

        $request = $event->getRequest();

        $parameters = [
            'redirectTo' => $request->attributes->get('_route'),
            'redirectParameters' => json_encode($request->attributes->get('_route_params'), \JSON_THROW_ON_ERROR),
        ];

        $redirectResponse = new RedirectResponse($this->router->generate('frontend.account.login.page', $parameters));

        $event->setResponse($redirectResponse);
    }

    public function maintenanceResolver(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if ($this->maintenanceModeResolver->shouldRedirect($request)) {
            $parameters = [];
            $route = $request->attributes->get('_route');
            if ($route !== null) {
                $parameters['redirectTo'] = $route;
                $requestParameters = $this->getRequestParameters($request);

                if ($requestParameters !== []) {
                    $parameters['redirectParameters'] = json_encode($requestParameters, \JSON_THROW_ON_ERROR);
                }
            }

            $redirectEvent = new MaintenanceRedirectEvent('frontend.maintenance.page', $parameters, Response::HTTP_TEMPORARY_REDIRECT);
            $this->eventDispatcher->dispatch($redirectEvent);

            $event->setResponse(
                new RedirectResponse($this->router->generate($redirectEvent->getRoute(), $redirectEvent->getParameters()), $redirectEvent->getStatus())
            );
        }
    }

    public function preventPageLoadingFromXmlHttpRequest(ControllerEvent $event): void
    {
        $request = $event->getRequest();

        if (!$request->isXmlHttpRequest()) {
            return;
        }

        $scope = $request->attributes->get(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, []);

        if (!\in_array(StorefrontRouteScope::ID, $scope, true)) {
            return;
        }

        $isAllowed = $request->attributes->getBoolean('XmlHttpRequest');
        if ($isAllowed) {
            return;
        }

        $route = $request->attributes->get('_route');
        $url = $request->getUri();
        $referer = $request->headers->get('referer');

        throw RoutingException::accessDeniedForXmlHttpRequest($route, $url, $referer);
    }

    // used to switch session token - when the context token expired
    public function replaceContextToken(SalesChannelContextResolvedEvent $event): void
    {
        $context = $event->getSalesChannelContext();

        // only update session if token expired and switched
        if ($event->getUsedToken() === $context->getToken()) {
            return;
        }

        $this->updateSession($context->getToken());
    }

    private function shouldRenewToken(SessionInterface $session, ?string $salesChannelId = null, ?string $tokenKey = null): bool
    {
        $keyToCheck = $tokenKey ?? PlatformRequest::HEADER_CONTEXT_TOKEN;

        if (!$session->has($keyToCheck) || $salesChannelId === null) {
            return true;
        }

        // When using per-channel tokens (binding enabled), we don't renew based on channel change
        // because each channel has its own token. We only renew if token doesn't exist for this key.
        if ($this->systemConfigService->getBool('core.systemWideLoginRegistration.isCustomerBoundToSalesChannel')) {
            // If we're checking a channel-specific key, token existence was already checked above
            if ($tokenKey !== null && $tokenKey !== PlatformRequest::HEADER_CONTEXT_TOKEN) {
                $expectedTokenKey = PlatformRequest::HEADER_CONTEXT_TOKEN . '-' . $salesChannelId;

                // Don't renew if the token key matches the current channel (token already exists for this channel)
                return $tokenKey !== $expectedTokenKey;
            }

            // For backward compatibility with default key, check if channel changed
            return $session->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID) !== $salesChannelId;
        }

        return false;
    }

    private function shouldRedirectLoginPage(\Throwable $ex): bool
    {
        if ($ex instanceof CustomerNotLoggedInRoutingException) {
            return true;
        }

        if ($ex instanceof CustomerNotLoggedInException) {
            return true;
        }

        return false;
    }

    /**
     * @return array<int|string, mixed>
     */
    private function getRequestParameters(Request $request): array
    {
        $requestParameters = $request->query->all();
        $routeParams = $request->attributes->get('_route_params');

        if (\is_array($routeParams)) {
            foreach ($routeParams as $key => $value) {
                // we don't want any default route parameter, e.g. _httpCache or _store
                if (\in_array($key, PlatformRequest::ATTRIBUTE_INTERNAL_ROUTE_PARAMS, true)) {
                    continue;
                }

                $requestParameters[$key] = $value;
            }
        }

        return $requestParameters;
    }
}
