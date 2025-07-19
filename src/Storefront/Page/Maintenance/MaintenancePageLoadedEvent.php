<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Maintenance;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\PageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

#[Package('framework')]
class MaintenancePageLoadedEvent extends PageLoadedEvent
{
    public function __construct(
        protected MaintenancePage $page,
        SalesChannelContext $salesChannelContext,
        Request $request
    ) {
        parent::__construct($salesChannelContext, $request);
    }

    public function getPage(): MaintenancePage
    {
        return $this->page;
    }
}
