<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Routing;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Framework\Event\BeforeSendResponseEvent;
use Shopware\Core\Framework\Routing\KernelListenerPriorities;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Storefront\Controller\ErrorController;
use Shopware\Storefront\Framework\Csrf\CsrfPlaceholderHandler;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

class StorefrontSubscriber implements EventSubscriberInterface
{
    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var ErrorController
     */
    private $errorController;

    /**
     * @var SalesChannelContextServiceInterface
     */
    private $contextService;

    /**
     * @var bool
     */
    private $kernelDebug;

    /**
     * @var CsrfPlaceholderHandler
     */
    private $csrfPlaceholderHandler;

    public function __construct(
        RequestStack $requestStack,
        RouterInterface $router,
        ErrorController $errorController,
        SalesChannelContextServiceInterface $contextService,
        CsrfPlaceholderHandler $csrfPlaceholderHandler,
        bool $kernelDebug
    ) {
        $this->requestStack = $requestStack;
        $this->router = $router;
        $this->errorController = $errorController;
        $this->contextService = $contextService;
        $this->kernelDebug = $kernelDebug;
        $this->csrfPlaceholderHandler = $csrfPlaceholderHandler;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [
                ['startSession', 40],
                ['maintenanceResolver'],
            ],
            KernelEvents::EXCEPTION => [
                ['showHtmlExceptionResponse', -100],
                ['customerNotLoggedInHandler'],
                ['maintenanceResolver'],
            ],
            KernelEvents::CONTROLLER => [
                ['preventPageLoadingFromXmlHttpRequest', KernelListenerPriorities::KERNEL_CONTROLLER_EVENT_SCOPE_VALIDATE],
            ],
            CustomerLoginEvent::class => [
                'updateSession',
            ],
            BeforeSendResponseEvent::class => [
                ['replaceCsrfToken'],
                ['setCanonicalUrl'],
            ],
        ];
    }

    public function maintenanceResolver(RequestEvent $event): void
    {
        $master = $this->requestStack->getMasterRequest();
        if (!$master || !$master->attributes->get(SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST)) {
            return;
        }

        $request = $event->getRequest();
        $route = $request->attributes->get('_route');

        if ($route && mb_strpos($route, 'frontend.maintenance') !== false) {
            return;
        }

        if ($request->isXmlHttpRequest()) {
            return;
        }

        $salesChannelMaintenance = $master->attributes
            ->get(SalesChannelRequest::ATTRIBUTE_SALES_CHANNEL_MAINTENANCE);
        if (!$salesChannelMaintenance) {
            return;
        }

        $currentIp = $request->server->get('REMOTE_ADDR');

        $maintenanceWhiteList = $master->attributes
            ->get(SalesChannelRequest::ATTRIBUTE_SALES_CHANNEL_MAINTENANCE_IP_WHITLELIST);
        if ($maintenanceWhiteList) {
            $maintenanceWhiteList = json_decode($maintenanceWhiteList, true);

            if (in_array($currentIp, $maintenanceWhiteList, true)) {
                return;
            }
        }

        $redirect = new RedirectResponse($this->router->generate('frontend.maintenance.page'));
        $event->setResponse($redirect);
    }

    public function startSession(): void
    {
        $master = $this->requestStack->getMasterRequest();

        if (!$master) {
            return;
        }
        if (!$master->attributes->get(SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST)) {
            return;
        }

        if (!$master->hasSession()) {
            return;
        }

        $session = $master->getSession();
        $applicationId = $master->attributes->get(PlatformRequest::ATTRIBUTE_OAUTH_CLIENT_ID);

        if (!$session->isStarted()) {
            $session->setName('session-' . $applicationId);
            $session->start();
            $session->set('sessionId', $session->getId());
        }

        if (!$session->has(PlatformRequest::HEADER_CONTEXT_TOKEN)) {
            $token = Random::getAlphanumericString(32);
            $session->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $token);
        }

        $master->headers->set(
            PlatformRequest::HEADER_CONTEXT_TOKEN,
            $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN)
        );
    }

    public function updateSession(CustomerLoginEvent $event): void
    {
        $master = $this->requestStack->getMasterRequest();
        if (!$master) {
            return;
        }
        if (!$master->attributes->get(SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST)) {
            return;
        }

        if (!$master->hasSession()) {
            return;
        }

        $session = $master->getSession();
        $session->migrate();
        $session->set('sessionId', $session->getId());

        $token = $event->getContextToken();
        $session->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $token);
        $master->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $token);
    }

    public function showHtmlExceptionResponse(ExceptionEvent $event): void
    {
        if ($this->kernelDebug) {
            return;
        }

        if (!$event->getRequest()->attributes->has(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT)) {
            //When no saleschannel context is resolved, we need to resolve it now.
            $this->setSalesChannelContext($event);
        }

        if ($event->getRequest()->attributes->has(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT)) {
            $event->stopPropagation();
            $response = $this->errorController->error(
                $event->getThrowable(),
                $this->requestStack->getMasterRequest(),
                $event->getRequest()->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT)
            );
            $event->setResponse($response);
        }
    }

    public function customerNotLoggedInHandler(ExceptionEvent $event): void
    {
        if (!$event->getRequest()->attributes->has(SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST)) {
            return;
        }

        if (!$event->getThrowable() instanceof CustomerNotLoggedInException) {
            return;
        }

        $request = $event->getRequest();

        $parameters = [
            'redirectTo' => $request->attributes->get('_route'),
            'redirectParameters' => json_encode($request->attributes->get('_route_params')),
        ];

        $redirectResponse = new RedirectResponse($this->router->generate('frontend.account.login.page', $parameters));

        $event->setResponse($redirectResponse);
    }

    public function preventPageLoadingFromXmlHttpRequest(ControllerEvent $event): void
    {
        if (!$event->getRequest()->isXmlHttpRequest()) {
            return;
        }

        if (!$event->getRequest()->attributes->has(SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST)) {
            return;
        }

        $controller = $event->getController();

        // happens if Controller is a closure
        if (!is_array($controller)) {
            return;
        }

        $isAllowed = $event->getRequest()->attributes->getBoolean('XmlHttpRequest', false);

        if ($isAllowed) {
            return;
        }

        throw new AccessDeniedHttpException('PageController can\'t be requested via XmlHttpRequest.');
    }

    public function setCanonicalUrl(BeforeSendResponseEvent $event): void
    {
        if (!$event->getResponse()->isSuccessful()) {
            return;
        }

        if ($canonical = $event->getRequest()->attributes->get(SalesChannelRequest::ATTRIBUTE_CANONICAL_LINK)) {
            $canonical = sprintf('<%s>; rel="canonical"', $canonical);
            $event->getResponse()->headers->set('Link', $canonical);
        }
    }

    public function replaceCsrfToken(BeforeSendResponseEvent $event): void
    {
        $event->setResponse(
            $this->csrfPlaceholderHandler->replaceCsrfToken($event->getResponse())
        );
    }

    private function setSalesChannelContext(ExceptionEvent $event): void
    {
        $contextToken = $event->getRequest()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN);
        $salesChannelId = $event->getRequest()->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID);

        $context = $this->contextService->get(
            $salesChannelId,
            $contextToken,
            $event->getRequest()->headers->get(PlatformRequest::HEADER_LANGUAGE_ID)
        );
        $event->getRequest()->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $context);
    }
}
