<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Event;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

class CartCreatedEvent extends Event implements ShopwareEvent
{
    /**
     * @var Cart
     */
    protected $cart;

    /**
     * @var SalesChannelContext
     */
    protected $salesChannelContext;

    public function __construct(Cart $cart, SalesChannelContext $salesChannelContext)
    {
        $this->cart = $cart;
        $this->salesChannelContext = $salesChannelContext;
    }

    public function getCart(): Cart
    {
        return $this->cart;
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
