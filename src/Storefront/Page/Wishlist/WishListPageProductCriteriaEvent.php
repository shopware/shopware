<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Wishlist;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class WishListPageProductCriteriaEvent extends NestedEvent implements ShopwareSalesChannelEvent
{
    private Criteria $criteria;

    private SalesChannelContext $salesChannelContext;

    private Request $request;

    public function __construct(
        Criteria $criteria,
        SalesChannelContext $salesChannelContext,
        Request $request
    ) {
        $this->criteria = $criteria;
        $this->salesChannelContext = $salesChannelContext;
        $this->request = $request;
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

    public function getRequest(): Request
    {
        return $this->request;
    }
}
