<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\Overview;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class AccountOverviewPageLoadedEvent extends NestedEvent
{
    /**
     * @var AccountOverviewPage
     */
    protected $page;

    /**
     * @var SalesChannelContext
     */
    protected $salesChannelContext;

    /**
     * @var Request
     */
    protected $request;

    public function __construct(AccountOverviewPage $page, SalesChannelContext $salesChannelContext, Request $request)
    {
        $this->page = $page;
        $this->salesChannelContext = $salesChannelContext;
        $this->request = $request;
    }

    public function getPage(): AccountOverviewPage
    {
        return $this->page;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }

    public function getContext(): Context
    {
        return $this->salesChannelContext->getContext();
    }

    public function getRequest(): Request
    {
        return $this->request;
    }
}
