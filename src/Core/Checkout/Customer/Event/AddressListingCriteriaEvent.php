<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('customer-order')]
class AddressListingCriteriaEvent extends NestedEvent implements ShopwareSalesChannelEvent
{
    public function __construct(
        private readonly Criteria $criteria,
        private readonly SalesChannelContext $salesChannelContext
    ) {
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
