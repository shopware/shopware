<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('buyers-experience')]
class CountryCriteriaEvent extends Event implements ShopwareSalesChannelEvent
{
    public function __construct(
        private readonly Request $request,
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

    public function getRequest(): Request
    {
        return $this->request;
    }
}
