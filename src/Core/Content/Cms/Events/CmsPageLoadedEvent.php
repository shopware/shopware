<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\Events;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class CmsPageLoadedEvent extends NestedEvent
{
    /**
     * @var Request
     */
    protected $request;

    /**
     * @var EntityCollection
     */
    protected $result;

    /**
     * @var SalesChannelContext
     */
    protected $salesChannelContext;

    public function __construct(Request $request, EntityCollection $result, SalesChannelContext $salesChannelContext)
    {
        $this->request = $request;
        $this->result = $result;
        $this->salesChannelContext = $salesChannelContext;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getResult(): EntityCollection
    {
        return $this->result;
    }

    public function getContext(): Context
    {
        return $this->salesChannelContext->getContext();
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }
}
