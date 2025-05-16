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
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

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
        private readonly SystemConfigService $systemConfigService
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
            $session->setName('session-');
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

        if ($this->shouldRenewToken($session, $salesChannelId)) {
            $token = Random::getAlphanumericString(32);
            $session->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $token);
            $session->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID, $salesChannelId);
        }

        $contextToken = $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN);
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
        if ($this->maintenanceModeResolver->shouldRedirect($event->getRequest())) {
            $event->setResponse(
                new RedirectResponse($this->router->generate('frontend.maintenance.page'), Response::HTTP_TEMPORARY_REDIRECT)
            );
        }
    }

    public function preventPageLoadingFromXmlHttpRequest(ControllerEvent $event): void
    {
        if (!$event->getRequest()->isXmlHttpRequest()) {
            return;
        }

        $scope = $event->getRequest()->attributes->get(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, []);

        if (!\in_array(StorefrontRouteScope::ID, $scope, true)) {
            return;
        }

        $isAllowed = $event->getRequest()->attributes->getBoolean('XmlHttpRequest');
        if ($isAllowed) {
            return;
        }

        throw RoutingException::accessDeniedForXmlHttpRequest();
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

    private function shouldRenewToken(SessionInterface $session, ?string $salesChannelId = null): bool
    {
        if (!$session->has(PlatformRequest::HEADER_CONTEXT_TOKEN) || $salesChannelId === null) {
            return true;
        }

        if ($this->systemConfigService->get('core.systemWideLoginRegistration.isCustomerBoundToSalesChannel')) {
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
}
