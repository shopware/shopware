<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Checkout\Confirm;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class CheckoutConfirmPageLoadedEvent extends NestedEvent
{
    /**
     * @var CheckoutConfirmPage
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

    public function __construct(CheckoutConfirmPage $page, SalesChannelContext $salesChannelContext, Request $request)
    {
        $this->page = $page;
        $this->salesChannelContext = $salesChannelContext;
        $this->request = $request;
    }

    public function getPage(): CheckoutConfirmPage
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
