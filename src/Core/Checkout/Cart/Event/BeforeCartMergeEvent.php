<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Event;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

class BeforeCartMergeEvent extends Event implements ShopwareSalesChannelEvent
{
    protected Cart $customerCart;

    protected Cart $guestCart;

    protected LineItemCollection $mergeableLineItems;

    protected SalesChannelContext $context;

    /**
     * @internal
     */
    public function __construct(Cart $customerCart, Cart $guestCart, LineItemCollection $mergeableLineItems, SalesChannelContext $context)
    {
        $this->customerCart = $customerCart;
        $this->guestCart = $guestCart;
        $this->mergeableLineItems = $mergeableLineItems;
        $this->context = $context;
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
