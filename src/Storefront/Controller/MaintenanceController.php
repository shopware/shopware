<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Framework\Adapter\Kernel\HttpCacheKernel;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Util\Json;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\Subscriber\SalesChannelMaintenancePasswordAccessSubscriber;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Framework\Routing\MaintenanceModeResolver;
use Shopware\Storefront\Framework\Routing\StorefrontRouteScope;
use Shopware\Storefront\Page\Maintenance\MaintenancePageLoadedHook;
use Shopware\Storefront\Page\Maintenance\MaintenancePageLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a store-api route to get or put data
 */
#[Package('discovery')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID]])]
class MaintenanceController extends StorefrontController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly MaintenancePageLoader $maintenancePageLoader,
        private readonly MaintenanceModeResolver $maintenanceModeResolver,
        private readonly RateLimiter $rateLimiter
    ) {
    }

    #[Route(
        path: '/maintenance',
        name: 'frontend.maintenance.page',
        defaults: [
            PlatformRequest::ATTRIBUTE_IS_ALLOWED_IN_MAINTENANCE => true,
            PlatformRequest::ATTRIBUTE_HTTP_CACHE => true,
        ],
        methods: [Request::METHOD_GET]
    )]
    public function renderMaintenancePage(Request $request, SalesChannelContext $context): ?Response
    {
        if ($this->maintenanceModeResolver->shouldRedirectToShop($request)) {
            // the redirect only holds while maintenance is off, it must not outlive that in the HTTP cache
            $request->attributes->set(PlatformRequest::ATTRIBUTE_HTTP_CACHE, false);

            if ($request->query->getString('redirectTo') !== '') {
                return $this->createActionResponse($request);
            }

            return $this->redirectToRoute('frontend.home.page');
        }

        $salesChannelId = $context->getSalesChannelId();
        $maintenanceLayoutId = $this->systemConfigService->getString('core.basicInformation.maintenancePage', $salesChannelId);

        if ($maintenanceLayoutId === '') {
            $response = $this->renderStorefront(
                '@Storefront/storefront/page/error/error-maintenance.html.twig',
                $this->getMaintenancePasswordTemplateData($request)
            );

            $response->setStatusCode(Response::HTTP_SERVICE_UNAVAILABLE, 'Service Temporarily Unavailable');
            $response->headers->set('Retry-After', '3600');

            $this->addAllowlistIpHeader($request, $response);
            $this->disableCacheForPasswordForm($request, $response);

            return $response;
        }

        $maintenancePage = $this->maintenancePageLoader->load($maintenanceLayoutId, $request, $context);

        $this->hook(new MaintenancePageLoadedHook($maintenancePage, $context));

        $response = $this->renderStorefront(
            '@Storefront/storefront/page/error/error-maintenance.html.twig',
            ['page' => $maintenancePage] + $this->getMaintenancePasswordTemplateData($request)
        );

        $response->setStatusCode(Response::HTTP_SERVICE_UNAVAILABLE, 'Service Temporarily Unavailable');
        $response->headers->set('Retry-After', '3600');

        $this->addAllowlistIpHeader($request, $response);
        $this->disableCacheForPasswordForm($request, $response);

        return $response;
    }

    #[Route(
        path: '/maintenance/password',
        name: 'frontend.maintenance.password.page',
        defaults: [PlatformRequest::ATTRIBUTE_IS_ALLOWED_IN_MAINTENANCE => true],
        methods: [Request::METHOD_GET]
    )]
    public function renderMaintenancePasswordPage(Request $request): Response
    {
        if (!$this->isMaintenancePasswordRequired($request)) {
            return $this->redirectToRoute('frontend.maintenance.page');
        }

        $response = $this->renderStorefront('@Storefront/storefront/page/error/error-maintenance-password.html.twig');

        $response->setStatusCode(Response::HTTP_SERVICE_UNAVAILABLE, 'Service Temporarily Unavailable');
        $response->headers->set('Retry-After', '3600');
        $response->headers->set('Cache-Control', 'no-store, private');

        $this->addAllowlistIpHeader($request, $response);

        return $response;
    }

    #[Route(
        path: '/maintenance/password',
        name: 'frontend.maintenance.password',
        defaults: [PlatformRequest::ATTRIBUTE_IS_ALLOWED_IN_MAINTENANCE => true],
        methods: [Request::METHOD_POST]
    )]
    public function authenticateMaintenancePassword(Request $request): Response
    {
        $salesChannelId = (string) $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID, '');
        $passwordHash = (string) $request->attributes->get(SalesChannelRequest::ATTRIBUTE_SALES_CHANNEL_MAINTENANCE_PASSWORD, '');
        $password = $request->request->getString('password');
        // @phpstan-ignore shopware.unsafeRequestHasSession (A successful password submission needs a session to store the access grant.)
        $session = $request->hasSession() ? $request->getSession() : null;
        $limiterKey = Hasher::hash($salesChannelId . ':' . (string) $request->getClientIp());
        $this->rateLimiter->ensureAcceptedIfConfigured(RateLimiter::MAINTENANCE_PASSWORD, $limiterKey);

        // The maintenance page forwards to the shop once the session grant is stored
        $redirectRoute = 'frontend.maintenance.page';

        if ($session !== null && $salesChannelId !== '' && $passwordHash !== '' && mb_strlen($password) <= PasswordHasherInterface::MAX_PASSWORD_LENGTH && password_verify($password, $passwordHash)) {
            $session->migrate(true);
            $passwordGeneration = (string) $request->attributes->get(SalesChannelRequest::ATTRIBUTE_SALES_CHANNEL_MAINTENANCE_PASSWORD_GENERATION, '');
            $session->set(SalesChannelMaintenancePasswordAccessSubscriber::SESSION_KEY_PREFIX . $salesChannelId, $passwordHash . ':' . $passwordGeneration);
            $this->rateLimiter->resetIfConfigured(RateLimiter::MAINTENANCE_PASSWORD, $limiterKey);
        } else {
            $redirectRoute = 'frontend.maintenance.password.page';

            if ($session instanceof FlashBagAwareSessionInterface) {
                $session->getFlashBag()->add('danger', 'general.maintenancePasswordInvalid');
            }
        }

        $response = $this->redirectToRoute($redirectRoute);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    /**
     * Route for standalone cms pages during maintenance
     */
    #[Route(
        path: '/maintenance/singlepage/{id}',
        name: 'frontend.maintenance.singlepage',
        defaults: [
            PlatformRequest::ATTRIBUTE_IS_ALLOWED_IN_MAINTENANCE => true,
            PlatformRequest::ATTRIBUTE_HTTP_CACHE => true,
        ],
        methods: [Request::METHOD_GET]
    )]
    public function renderSinglePage(string $id, Request $request, SalesChannelContext $salesChannelContext): Response
    {
        if (!$id) {
            throw RoutingException::missingRequestParameter('id');
        }

        if ($this->isMaintenancePasswordRequired($request)) {
            return $this->redirectToRoute('frontend.maintenance.page');
        }

        $cmsPage = $this->maintenancePageLoader->load($id, $request, $salesChannelContext);

        $this->hook(new MaintenancePageLoadedHook($cmsPage, $salesChannelContext));

        $response = $this->renderStorefront(
            '@Storefront/storefront/page/content/single-cms-page.html.twig',
            ['page' => $cmsPage]
        );

        $this->addAllowlistIpHeader($request, $response);

        return $response;
    }

    /**
     * @return array{maintenancePasswordRequired: bool}
     */
    private function getMaintenancePasswordTemplateData(Request $request): array
    {
        return [
            'maintenancePasswordRequired' => $this->isMaintenancePasswordRequired($request),
        ];
    }

    private function isMaintenancePasswordRequired(Request $request): bool
    {
        $passwordHash = (string) $request->attributes->get(SalesChannelRequest::ATTRIBUTE_SALES_CHANNEL_MAINTENANCE_PASSWORD, '');

        return $passwordHash !== '' && $this->maintenanceModeResolver->isMaintenanceRequest($request);
    }

    private function disableCacheForPasswordForm(Request $request, Response $response): void
    {
        if ((string) $request->attributes->get(SalesChannelRequest::ATTRIBUTE_SALES_CHANNEL_MAINTENANCE_PASSWORD, '') !== '') {
            $response->headers->set('Cache-Control', 'no-store, private');
        }
    }

    private function addAllowlistIpHeader(Request $request, Response $response): void
    {
        $ips = $request->attributes->get(SalesChannelRequest::ATTRIBUTE_SALES_CHANNEL_MAINTENANCE_IP_ALLOWLIST)
            // @deprecated tag:v6.8.0 - remove the fallback to the deprecated attribute
            ?? $request->attributes->get(SalesChannelRequest::ATTRIBUTE_SALES_CHANNEL_MAINTENANCE_IP_WHITLELIST);

        if ($ips) {
            $ips = implode(',', Json::decodeToList((string) $ips));

            $response->headers->set(HttpCacheKernel::MAINTENANCE_ALLOWLIST_HEADER, $ips);
            // @deprecated tag:v6.8.0 - remove setting the deprecated header
            $response->headers->set(HttpCacheKernel::MAINTENANCE_WHITELIST_HEADER, $ips);
        }
    }
}
