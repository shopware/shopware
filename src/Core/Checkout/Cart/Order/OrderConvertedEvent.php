<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Order;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class OrderConvertedEvent extends NestedEvent
{
    private Cart $convertedCart;

    public function __construct(
        private OrderEntity $order,
        private Cart $cart,
        private Context $context
    ) {
        $this->convertedCart = clone $cart;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getOrder(): OrderEntity
    {
        return $this->order;
    }

    public function getCart(): Cart
    {
        return $this->cart;
    }

    public function getConvertedCart(): Cart
    {
        return $this->convertedCart;
    }

    public function setConvertedCart(Cart $convertedCart): void
    {
        $this->convertedCart = $convertedCart;
    }
}
