<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Event;

use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Allows the manipulation of the sales channel context after it was assembled from the order
 */
#[Package('checkout')]
class SalesChannelContextAssembledEvent extends Event implements ShopwareSalesChannelEvent
{
    /**
     * @internal
     */
    public function __construct(
        private readonly OrderEntity $order,
        private readonly SalesChannelContext $salesChannelContext,
    ) {
    }

    public function getOrder(): OrderEntity
    {
        return $this->order;
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
