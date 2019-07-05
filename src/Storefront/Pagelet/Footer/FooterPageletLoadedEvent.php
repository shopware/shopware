<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Footer;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class FooterPageletLoadedEvent extends NestedEvent
{
    /**
     * @var FooterPagelet
     */
    protected $pagelet;

    /**
     * @var SalesChannelContext
     */
    protected $salesChannelContext;

    /**
     * @var Request
     */
    protected $request;

    public function __construct(FooterPagelet $page, SalesChannelContext $salesChannelContext, Request $request)
    {
        $this->pagelet = $page;
        $this->salesChannelContext = $salesChannelContext;
        $this->request = $request;
    }

    public function getPagelet(): FooterPagelet
    {
        return $this->pagelet;
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
