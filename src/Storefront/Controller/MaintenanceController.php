<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Content\Cms\Exception\PageNotFoundException;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Framework\Cache\Annotation\HttpCache;
use Shopware\Storefront\Framework\Routing\MaintenanceModeResolver;
use Shopware\Storefront\Page\Maintenance\MaintenancePageLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"storefront"})
 */
class MaintenanceController extends StorefrontController
{
    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    /**
     * @var MaintenancePageLoader
     */
    private $maintenancePageLoader;

    /**
     * @var MaintenanceModeResolver
     */
    private $maintenanceModeResolver;

    public function __construct(
        SystemConfigService $systemConfigService,
        MaintenancePageLoader $maintenancePageLoader,
        MaintenanceModeResolver $maintenanceModeResolver
    ) {
        $this->systemConfigService = $systemConfigService;
        $this->maintenancePageLoader = $maintenancePageLoader;
        $this->maintenanceModeResolver = $maintenanceModeResolver;
    }

    /**
     * @Since("6.1.0.0")
     * @HttpCache()
     * @Route("/maintenance", name="frontend.maintenance.page", methods={"GET"}, defaults={"allow_maintenance"=true})
     */
    public function renderMaintenancePage(Request $request, SalesChannelContext $context): ?Response
    {
        $salesChannel = $context->getSalesChannel();

        if ($this->maintenanceModeResolver->shouldRedirectToShop($request)) {
            return $this->redirectToRoute('frontend.home.page');
        }

        $salesChannelId = $salesChannel->getId();
        $maintenanceLayoutId = $this->systemConfigService->getString('core.basicInformation.maintenancePage', $salesChannelId);

        if ($maintenanceLayoutId === '') {
            $response = $this->renderStorefront(
                '@Storefront/storefront/page/error/error-maintenance.html.twig'
            );

            $response->setStatusCode(Response::HTTP_SERVICE_UNAVAILABLE, 'Service Temporarily Unavailable');
            $response->headers->set('Retry-After', '3600');

            return $response;
        }

        $maintenancePage = $this->maintenancePageLoader->load($maintenanceLayoutId, $request, $context);

        $response = $this->renderStorefront(
            '@Storefront/storefront/page/error/error-maintenance.html.twig',
            ['page' => $maintenancePage]
        );

        $response->setStatusCode(Response::HTTP_SERVICE_UNAVAILABLE, 'Service Temporarily Unavailable');
        $response->headers->set('Retry-After', '3600');

        return $response;
    }

    /**
     * @Since("6.1.0.0")
     * Route for stand alone cms pages during maintenance
     *
     * @HttpCache()
     * @Route("/maintenance/singlepage/{id}", name="frontend.maintenance.singlepage", methods={"GET"}, defaults={"allow_maintenance"=true})
     *
     * @throws MissingRequestParameterException
     * @throws PageNotFoundException
     */
    public function renderSinglePage(string $id, Request $request, SalesChannelContext $salesChannelContext): Response
    {
        if (!$id) {
            throw new MissingRequestParameterException('Parameter id missing');
        }

        $cmsPage = $this->maintenancePageLoader->load($id, $request, $salesChannelContext);

        return $this->renderStorefront(
            '@Storefront/storefront/page/content/single-cms-page.html.twig',
            ['page' => $cmsPage]
        );
    }
}
