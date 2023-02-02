<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Event;

use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Framework\Context;
use Symfony\Contracts\EventDispatcher\Event;

abstract class DocumentOrderEvent extends Event
{
    private OrderCollection $orders;

    private Context $context;

    public function __construct(OrderCollection $orders, Context $context)
    {
        $this->orders = $orders;
        $this->context = $context;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getOrders(): OrderCollection
    {
        return $this->orders;
    }
}
