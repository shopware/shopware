<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Event;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
class AfterLineItemQuantityChangedEvent implements ShopwareSalesChannelEvent
{
    /**
     * @var LineItem[]
     */
    protected $items;

    /**
     * @var Cart
     */
    protected $cart;

    /**
     * @var SalesChannelContext
     */
    protected $salesChannelContext;

    /**
     * @param LineItem[] $items
     */
    public function __construct(
        Cart $cart,
        array $items,
        SalesChannelContext $salesChannelContext
    ) {
        $this->cart = $cart;
        $this->items = $items;
        $this->salesChannelContext = $salesChannelContext;
    }

    public function getCart(): Cart
    {
        return $this->cart;
    }

    /**
     * @return LineItem[]
     */
    public function getItems(): array
    {
        return $this->items;
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
