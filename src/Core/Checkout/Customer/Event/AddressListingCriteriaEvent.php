<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class AddressListingCriteriaEvent extends NestedEvent implements ShopwareSalesChannelEvent
{
    private Criteria $criteria;

    private SalesChannelContext $salesChannelContext;

    public function __construct(Criteria $criteria, SalesChannelContext $salesChannelContext)
    {
        $this->criteria = $criteria;
        $this->salesChannelContext = $salesChannelContext;
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
