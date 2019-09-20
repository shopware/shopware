<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\Order;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\PageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

class AccountOrderPageLoadedEvent extends PageLoadedEvent
{
    /**
     * @var AccountOrderPage
     */
    protected $page;

    public function __construct(AccountOrderPage $page, SalesChannelContext $salesChannelContext, Request $request)
    {
        $this->page = $page;
        parent::__construct($salesChannelContext, $request);
    }

    public function getPage(): AccountOrderPage
    {
        return $this->page;
    }
}
