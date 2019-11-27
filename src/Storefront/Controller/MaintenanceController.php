<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Framework\Cache\Annotation\HttpCache;
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

    public function __construct(
        SystemConfigService $systemConfigService,
        MaintenancePageLoader $maintenancePageLoader
    ) {
        $this->systemConfigService = $systemConfigService;
        $this->maintenancePageLoader = $maintenancePageLoader;
    }

    /**
     * @HttpCache()
     * @Route("/maintenance", name="frontend.maintenance.page", methods={"GET"}, defaults={"bypassMaintenance"="1"})
     */
    public function renderMaintenancePage(Request $request, SalesChannelContext $context): ?Response
    {
        $salesChannel = $context->getSalesChannel();

        if (!$salesChannel->isMaintenance()) {
            return $this->redirectToRoute('frontend.home.page');
        }

        $salesChannelId = $salesChannel->getId();
        $maintenanceLayoutId = $this->systemConfigService->get('core.basicInformation.maintenancePage', $salesChannelId);

        if (!$maintenanceLayoutId) {
            return $this->renderStorefront(
                '@Storefront/storefront/page/error/error-maintenance.html.twig'
            );
        }

        $maintenancePage = $this->maintenancePageLoader->load((string) $maintenanceLayoutId, $request, $context);

        $response = $this->renderStorefront(
            '@Storefront/storefront/page/error/error-maintenance.html.twig',
            ['page' => $maintenancePage]
        );

        $response->setStatusCode(Response::HTTP_OK);

        return $response;
    }
}
