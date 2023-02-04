<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Events;

use Shopware\Core\Content\Product\SalesChannel\CrossSelling\CrossSellingElementCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('inventory')]
class ProductCrossSellingsLoadedEvent extends Event implements ShopwareSalesChannelEvent
{
    public function __construct(
        private readonly CrossSellingElementCollection $result,
        private readonly SalesChannelContext $salesChannelContext
    ) {
    }

    public function getContext(): Context
    {
        return $this->salesChannelContext->getContext();
    }

    public function getCrossSellings(): CrossSellingElementCollection
    {
        return $this->result;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }
}
