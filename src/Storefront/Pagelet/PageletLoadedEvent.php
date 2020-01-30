<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

abstract class PageletLoadedEvent extends NestedEvent
{
    /**
     * @var SalesChannelContext
     */
    protected $salesChannelContext;

    /**
     * @var Request
     */
    protected $request;

    public function __construct(SalesChannelContext $salesChannelContext, Request $request)
    {
        $this->salesChannelContext = $salesChannelContext;
        $this->request = $request;
    }

    /**
     * @return Pagelet
     */
    abstract public function getPagelet();

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
