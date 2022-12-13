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
use Shopware\Core\Framework\Routing\Event\SalesChannelContextResolvedEvent;
use Shopware\Core\Framework\Routing\KernelListenerPriorities;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Shopware\Storefront\Framework\Csrf\CsrfPlaceholderHandler;
use Shopware\Storefront\Framework\Routing\NotFound\NotFoundSubscriber;
use Shopware\Storefront\Theme\StorefrontPluginRegistryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

/**
 * @deprecated tag:v6.5.0 - reason:becomes-internal - EventSubscribers will become internal in v6.5.0
 */
class StorefrontSubscriber implements EventSubscriberInterface
{
    private RequestStack $requestStack;

    private RouterInterface $router;

    private CsrfPlaceholderHandler $csrfPlaceholderHandler;

    private MaintenanceModeResolver $maintenanceModeResolver;

    private HreflangLoaderInterface $hreflangLoader;

    private ShopIdProvider $shopIdProvider;

    private ActiveAppsLoader $activeAppsLoader;

    private SystemConfigService $systemConfigService;

    private StorefrontPluginRegistryInterface $themeRegistry;

    private NotFoundSubscriber $notFoundSubscriber;

    /**
     * @internal
     */
    public function __construct(
        RequestStack $requestStack,
        RouterInterface $router,
        CsrfPlaceholderHandler $csrfPlaceholderHandler,
        HreflangLoaderInterface $hreflangLoader,
        MaintenanceModeResolver $maintenanceModeResolver,
        ShopIdProvider $shopIdProvider,
        ActiveAppsLoader $activeAppsLoader,
        SystemConfigService $systemConfigService,
        StorefrontPluginRegistryInterface $themeRegistry,
        NotFoundSubscriber $notFoundSubscriber
    ) {
        $this->requestStack = $requestStack;
        $this->router = $router;
        $this->csrfPlaceholderHandler = $csrfPlaceholderHandler;
        $this->maintenanceModeResolver = $maintenanceModeResolver;
        $this->hreflangLoader = $hreflangLoader;
        $this->shopIdProvider = $shopIdProvider;
        $this->activeAppsLoader = $activeAppsLoader;
        $this->systemConfigService = $systemConfigService;
        $this->themeRegistry = $themeRegistry;
        $this->notFoundSubscriber = $notFoundSubscriber;
    }

    public static function getSubscribedEvents(): array
    {
        if (Feature::isActive('v6.5.0.0')) {
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
                BeforeSendResponseEvent::class => [
                    ['setCanonicalUrl'],
                ],
                StorefrontRenderEvent::class => [
                    ['addHreflang'],
                    ['addShopIdParameter'],
                    ['addIconSetConfig'],
                ],
                SalesChannelContextResolvedEvent::class => [
                    ['replaceContextToken'],
                ],
            ];
        }

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
                ['addIconSetConfig'],
            ],
            SalesChannelContextResolvedEvent::class => [
                ['replaceContextToken'],
            ],
        ];
    }

    public function startSession(): void
    {
        $master = $this->requestStack->getMainRequest();

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

        if (!$session->isStarted()) {
            $session->setName('session-');
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

        if ($this->shouldRenewToken($session, $salesChannelId)) {
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

    public function updateSessionAfterLogout(): void
    {
        $newToken = Random::getAlphanumericString(32);

        $this->updateSession($newToken, true);
    }

    public function updateSession(string $token, bool $destroyOldSession = false): void
    {
        $master = $this->requestStack->getMainRequest();
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
        $session->migrate($destroyOldSession);
        $session->set('sessionId', $session->getId());

        $session->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $token);
        $master->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $token);
    }

    /**
     * @deprecated tag:v6.5.0 - reason:remove-subscriber - Use `NotFoundSubscriber::onError` instead
     */
    public function showHtmlExceptionResponse(ExceptionEvent $event): void
    {
        $this->notFoundSubscriber->onError($event);
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

        /** @var list<string> $scope */
        $scope = $event->getRequest()->attributes->get(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, []);

        if (!\in_array(StorefrontRouteScope::ID, $scope, true)) {
            return;
        }

        $controller = $event->getController();

        // happens if Controller is a closure
        if (!\is_array($controller)) {
            return;
        }

        $isAllowed = $event->getRequest()->attributes->getBoolean('XmlHttpRequest', false);

        if ($isAllowed) {
            return;
        }

        throw new AccessDeniedHttpException('PageController can\'t be requested via XmlHttpRequest.');
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

    /**
     * @deprecated tag:v6.5.0 - replaceCsrfToken method will be removed as the csrf system will be removed in favor for the samesite approach
     */
    public function replaceCsrfToken(BeforeSendResponseEvent $event): void
    {
        if (Feature::isActive('v6.5.0.0')) {
            return;
        }

        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0')
        );

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
    }

    public function addIconSetConfig(StorefrontRenderEvent $event): void
    {
        $request = $event->getRequest();

        // get name if theme is not inherited
        $theme = $request->attributes->get(SalesChannelRequest::ATTRIBUTE_THEME_NAME);

        if (!$theme) {
            // get theme name from base theme because for inherited themes the name is always null
            $theme = $request->attributes->get(SalesChannelRequest::ATTRIBUTE_THEME_BASE_NAME);
        }

        if (!$theme) {
            return;
        }

        $themeConfig = $this->themeRegistry->getConfigurations()->getByTechnicalName($theme);

        if (!$themeConfig) {
            return;
        }

        $iconConfig = [];
        foreach ($themeConfig->getIconSets() as $pack => $path) {
            $iconConfig[$pack] = [
                'path' => $path,
                'namespace' => $theme,
            ];
        }

        $event->setParameter('themeIconConfig', $iconConfig);
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
}
