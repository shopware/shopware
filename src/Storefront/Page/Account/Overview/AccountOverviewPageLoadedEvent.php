<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\Overview;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\PageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

#[Package('customer-order')]
class AccountOverviewPageLoadedEvent extends PageLoadedEvent
{
    /**
     * @var AccountOverviewPage
     */
    protected $page;

    public function __construct(
        AccountOverviewPage $page,
        SalesChannelContext $salesChannelContext,
        Request $request
    ) {
        $this->page = $page;
        parent::__construct($salesChannelContext, $request);
    }

    public function getPage(): AccountOverviewPage
    {
        return $this->page;
    }
}
