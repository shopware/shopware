<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Event;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('checkout')]
class BeforeCartMergeEvent extends Event implements ShopwareSalesChannelEvent
{
    /**
     * @internal
     */
    public function __construct(
        protected Cart $customerCart,
        protected Cart $guestCart,
        protected LineItemCollection $mergeableLineItems,
        protected SalesChannelContext $context
    ) {
    }

    public function getCustomerCart(): Cart
    {
        return $this->customerCart;
    }

    public function getGuestCart(): Cart
    {
        return $this->guestCart;
    }

    public function getMergeableLineItems(): LineItemCollection
    {
        return $this->mergeableLineItems;
    }

    public function getContext(): Context
    {
        return $this->context->getContext();
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->context;
    }
}
