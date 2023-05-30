<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\Order;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\PageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

#[Package('customer-order')]
class AccountOrderDetailPageLoadedEvent extends PageLoadedEvent
{
    /**
     * @var AccountOrderDetailPage
     */
    protected $page;

    public function __construct(
        AccountOrderDetailPage $page,
        SalesChannelContext $salesChannelContext,
        Request $request
    ) {
        $this->page = $page;
        parent::__construct($salesChannelContext, $request);
    }

    public function getPage(): AccountOrderDetailPage
    {
        return $this->page;
    }
}
