<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Maintenance;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\PageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

#[Package('storefront')]
class MaintenancePageLoadedEvent extends PageLoadedEvent
{
    /**
     * @var MaintenancePage
     */
    protected $page;

    public function __construct(
        MaintenancePage $page,
        SalesChannelContext $salesChannelContext,
        Request $request
    ) {
        $this->page = $page;
        parent::__construct($salesChannelContext, $request);
    }

    public function getPage(): MaintenancePage
    {
        return $this->page;
    }
}
