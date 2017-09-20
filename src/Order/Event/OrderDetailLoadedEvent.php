<?php declare(strict_types=1);

namespace Shopware\Order\Event;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Order\Struct\OrderDetailCollection;
use Shopware\OrderDelivery\Event\OrderDeliveryBasicLoadedEvent;
use Shopware\OrderLineItem\Event\OrderLineItemBasicLoadedEvent;

class OrderDetailLoadedEvent extends NestedEvent
{
    const NAME = 'order.detail.loaded';

    /**
     * @var OrderDetailCollection
     */
    protected $orders;

    /**
     * @var TranslationContext
     */
    protected $context;

    public function __construct(OrderDetailCollection $orders, TranslationContext $context)
    {
        $this->orders = $orders;
        $this->context = $context;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getOrders(): OrderDetailCollection
    {
        return $this->orders;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getEvents(): ?NestedEventCollection
    {
        return new NestedEventCollection([
            new OrderBasicLoadedEvent($this->orders, $this->context),
            new OrderLineItemBasicLoadedEvent($this->orders->getLineItems(), $this->context),
            new OrderDeliveryBasicLoadedEvent($this->orders->getDeliveries(), $this->context),
        ]);
    }
}
