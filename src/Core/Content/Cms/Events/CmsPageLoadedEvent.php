<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\Events;

use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

#[Package('content')]
class CmsPageLoadedEvent extends NestedEvent implements ShopwareSalesChannelEvent
{
    /**
     * @var Request
     */
    protected $request;

    /**
     * @var EntityCollection<CmsPageEntity>
     */
    protected $result;

    /**
     * @var SalesChannelContext
     */
    protected $salesChannelContext;

    /**
     * @param EntityCollection<CmsPageEntity> $result
     */
    public function __construct(
        Request $request,
        EntityCollection $result,
        SalesChannelContext $salesChannelContext
    ) {
        $this->request = $request;
        $this->result = $result;
        $this->salesChannelContext = $salesChannelContext;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    /**
     * @return EntityCollection<CmsPageEntity>
     */
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
