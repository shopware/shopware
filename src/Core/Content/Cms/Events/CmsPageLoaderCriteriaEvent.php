<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\Events;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

#[Package('content')]
class CmsPageLoaderCriteriaEvent extends NestedEvent implements ShopwareSalesChannelEvent
{
    /**
     * @var Request
     */
    protected $request;

    /**
     * @var Criteria
     */
    protected $criteria;

    /**
     * @var SalesChannelContext
     */
    protected $salesChannelContext;

    public function __construct(
        Request $request,
        Criteria $criteria,
        SalesChannelContext $salesChannelContext
    ) {
        $this->request = $request;
        $this->criteria = $criteria;
        $this->salesChannelContext = $salesChannelContext;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getCriteria(): Criteria
    {
        return $this->criteria;
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
