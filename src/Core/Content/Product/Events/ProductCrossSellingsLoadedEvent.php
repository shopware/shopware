<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Events;

use Shopware\Core\Content\Product\SalesChannel\CrossSelling\CrossSellingElementCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

class ProductCrossSellingsLoadedEvent extends Event implements ShopwareSalesChannelEvent
{
    /**
     * @var CrossSellingElementCollection
     */
    private $result;

    /**
     * @var SalesChannelContext
     */
    private $salesChannelContext;

    public function __construct(CrossSellingElementCollection $result, SalesChannelContext $salesChannelContext)
    {
        $this->result = $result;
        $this->salesChannelContext = $salesChannelContext;
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
