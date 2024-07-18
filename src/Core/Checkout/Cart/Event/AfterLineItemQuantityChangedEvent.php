<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Event;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
class AfterLineItemQuantityChangedEvent implements ShopwareSalesChannelEvent, CartEvent
{
    /**
     * @var array<array<string, mixed>>
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
     * @param array<array<string, mixed>> $items
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
     * @return array<array<string, mixed>>
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
