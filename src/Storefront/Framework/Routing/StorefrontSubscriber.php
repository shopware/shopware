<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Routing;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerLogoutEvent;
use Shopware\Core\Content\Seo\HreflangLoaderInterface;
use Shopware\Core\Content\Seo\HreflangLoaderParameter;
use Shopware\Core\Framework\App\ActiveAppsLoader;
use Shopware\Core\Framework\App\Exception\AppUrlChangeDetectedException;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Event\BeforeSendResponseEvent;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\KernelListenerPriorities;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\ErrorController;
use Shopware\Storefront\Event\StorefrontRenderEvent;
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

    /**
     * @var MaintenanceModeResolver
     */
    private $maintenanceModeResolver;

    /**
     * @var HreflangLoaderInterface
     */
    private $hreflangLoader;

    /**
     * @var ShopIdProvider
     */
    private $shopIdProvider;

    /**
     * @var ActiveAppsLoader
     */
    private $activeAppsLoader;

    public function __construct(
        RequestStack $requestStack,
        RouterInterface $router,
        ErrorController $errorController,
        SalesChannelContextServiceInterface $contextService,
        CsrfPlaceholderHandler $csrfPlaceholderHandler,
        HreflangLoaderInterface $hreflangLoader,
        bool $kernelDebug,
        MaintenanceModeResolver $maintenanceModeResolver,
        ShopIdProvider $shopIdProvider,
        ActiveAppsLoader $activeAppsLoader
    ) {
        $this->requestStack = $requestStack;
        $this->router = $router;
        $this->errorController = $errorController;
        $this->contextService = $contextService;
        $this->kernelDebug = $kernelDebug;
        $this->csrfPlaceholderHandler = $csrfPlaceholderHandler;
        $this->maintenanceModeResolver = $maintenanceModeResolver;
        $this->hreflangLoader = $hreflangLoader;
        $this->shopIdProvider = $shopIdProvider;
        $this->activeAppsLoader = $activeAppsLoader;
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
                'updateSessionAfterLogin',
            ],
            CustomerLogoutEvent::class => [
                'updateSessionAfterLogout',
            ],
            BeforeSendResponseEvent::class => [
                ['replaceCsrfToken'],
                ['setCanonicalUrl'],
            ],
            StorefrontRenderEvent::class => [
                ['addHreflang'],
                ['addShopIdParameter'],
            ],
        ];
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

        $salesChannelId = $master->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID);
        if ($salesChannelId === null) {
            /** @var SalesChannelContext|null $salesChannelContext */
            $salesChannelContext = $master->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);
            if ($salesChannelContext !== null) {
                $salesChannelId = $salesChannelContext->getSalesChannel()->getId();
            }
        }

        if (!$session->has(PlatformRequest::HEADER_CONTEXT_TOKEN) || $session->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID) !== $salesChannelId) {
            $token = Random::getAlphanumericString(32);
            $session->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $token);
            $session->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID, $salesChannelId);
        }

        $master->headers->set(
            PlatformRequest::HEADER_CONTEXT_TOKEN,
            $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN)
        );
    }

    public function updateSessionAfterLogin(CustomerLoginEvent $event): void
    {
        $token = $event->getContextToken();

        $this->updateSession($token);
    }

    public function updateSessionAfterLogout(CustomerLogoutEvent $event): void
    {
        if (!Feature::isActive('FEATURE_NEXT_10058')) {
            return;
        }

        $newToken = $event->getSalesChannelContext()->getToken();

        $this->updateSession($newToken);
    }

    public function updateSession(string $token): void
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

    public function maintenanceResolver(RequestEvent $event): void
    {
        if ($this->maintenanceModeResolver->shouldRedirect($event->getRequest())) {
            $event->setResponse(
                new RedirectResponse(
                    $this->router->generate('frontend.maintenance.page'),
                    RedirectResponse::HTTP_TEMPORARY_REDIRECT
                )
            );
        }
    }

    public function preventPageLoadingFromXmlHttpRequest(ControllerEvent $event): void
    {
        if (!$event->getRequest()->isXmlHttpRequest()) {
            return;
        }

        /** @var RouteScope $scope */
        $scope = $event->getRequest()->attributes->get(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, new RouteScope(['scopes' => []]));
        if (!$scope->hasScope(StorefrontRouteScope::ID)) {
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
            $this->csrfPlaceholderHandler->replaceCsrfToken($event->getResponse(), $event->getRequest())
        );
    }

    public function addHreflang(StorefrontRenderEvent $event): void
    {
        $request = $event->getRequest();
        $route = $request->attributes->get('_route');

        if ($route === null) {
            return;
        }

        $routeParams = $request->attributes->get('_route_params', []);
        $salesChannelContext = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);
        $parameter = new HreflangLoaderParameter($route, $routeParams, $salesChannelContext);
        $event->setParameter('hrefLang', $this->hreflangLoader->load($parameter));
    }

    public function addShopIdParameter(StorefrontRenderEvent $event): void
    {
        if (!$this->activeAppsLoader->getActiveApps()) {
            return;
        }

        try {
            $shopId = $this->shopIdProvider->getShopId();
        } catch (AppUrlChangeDetectedException $e) {
            return;
        }

        $event->setParameter('appShopId', $shopId);
        /*
         * @deprecated tag:v6.4.0 use `appShopId` instead
         */
        $event->setParameter('swagShopId', $shopId);
    }

    private function setSalesChannelContext(ExceptionEvent $event): void
    {
        $contextToken = $event->getRequest()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN);
        $salesChannelId = $event->getRequest()->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID);

        $context = $this->contextService->get(
            $salesChannelId,
            $contextToken,
            $event->getRequest()->headers->get(PlatformRequest::HEADER_LANGUAGE_ID),
            $event->getRequest()->attributes->get(SalesChannelRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID)
        );
        $event->getRequest()->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $context);
    }
}
