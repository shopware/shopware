<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Event;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

class CartVerifyPersistEvent extends Event implements ShopwareSalesChannelEvent
{
    protected SalesChannelContext $context;

    protected Cart $cart;

    protected bool $shouldPersist;

    public function __construct(SalesChannelContext $context, Cart $cart, bool $shouldPersist)
    {
        $this->context = $context;
        $this->cart = $cart;
        $this->shouldPersist = $shouldPersist;
    }

    public function getContext(): Context
    {
        return $this->context->getContext();
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->context;
    }

    public function getCart(): Cart
    {
        return $this->cart;
    }

    public function shouldBePersisted(): bool
    {
        return $this->shouldPersist;
    }

    public function setShouldPersist(bool $persist): void
    {
        $this->shouldPersist = $persist;
    }
}
