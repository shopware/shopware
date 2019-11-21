<?php

namespace Shopware\Storefront\Controller;

use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Content\Cms\Exception\PageNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Page\Maintenance\MaintenancePageLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

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

    public function __construct
    (
        SystemConfigService $systemConfigService,
        MaintenancePageLoader $maintenancePageLoader
    )
    {

        $this->systemConfigService = $systemConfigService;
        $this->maintenancePageLoader = $maintenancePageLoader;
    }

    /**
     * @param Request $request
     * @param SalesChannelContext $context
     * @return Response|null
     * @throws PageNotFoundException
     */
    public function renderMaintenancePage(Request $request, SalesChannelContext $context): ?Response
    {
        $salesChannelId = $context->getSalesChannel()->getId();
        $maintenanceLayoutId = $this->systemConfigService->get('core.basicInformation.maintenancePage', $salesChannelId);

        if (!$maintenanceLayoutId) {
            throw new PageNotFoundException($maintenanceLayoutId);
        }

        $maintenancePage = $this->maintenancePageLoader->load($maintenanceLayoutId, $request, $context);

        $response = $this->renderStorefront(
            '@Storefront/storefront/page/error/error-maintenance.html.twig',
            ['page' => $maintenancePage]
        );

        $response->setStatusCode(Response::HTTP_OK);

        return $response;
    }
}
