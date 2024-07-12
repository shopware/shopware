<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Event;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartContextHashStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('checkout')]
class CartContextHashEvent extends Event implements ShopwareSalesChannelEvent
{
    public function __construct(
        protected readonly SalesChannelContext $salesChannelContext,
        protected readonly Cart $cart,
        protected CartContextHashStruct $hashStruct
    ) {
    }

    public function getContext(): Context
    {
        return $this->salesChannelContext->getContext();
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }

    public function getCart(): Cart
    {
        return $this->cart;
    }

    public function getHashStruct(): CartContextHashStruct
    {
        return $this->hashStruct;
    }

    public function setHashStruct(CartContextHashStruct $hashStruct): void
    {
        $this->hashStruct = $hashStruct;
    }
}
