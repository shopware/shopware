<?php declare(strict_types=1);

namespace Shopware\Order\Event\OrderState;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Mail\Event\Mail\MailBasicLoadedEvent;
use Shopware\Order\Collection\OrderStateDetailCollection;
use Shopware\Order\Event\Order\OrderBasicLoadedEvent;
use Shopware\Order\Event\OrderDelivery\OrderDeliveryBasicLoadedEvent;
use Shopware\Order\Event\OrderStateTranslation\OrderStateTranslationBasicLoadedEvent;

class OrderStateDetailLoadedEvent extends NestedEvent
{
    const NAME = 'order_state.detail.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var OrderStateDetailCollection
     */
    protected $orderStates;

    public function __construct(OrderStateDetailCollection $orderStates, TranslationContext $context)
    {
        $this->context = $context;
        $this->orderStates = $orderStates;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getOrderStates(): OrderStateDetailCollection
    {
        return $this->orderStates;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->orderStates->getMails()->count() > 0) {
            $events[] = new MailBasicLoadedEvent($this->orderStates->getMails(), $this->context);
        }
        if ($this->orderStates->getOrders()->count() > 0) {
            $events[] = new OrderBasicLoadedEvent($this->orderStates->getOrders(), $this->context);
        }
        if ($this->orderStates->getOrderDeliveries()->count() > 0) {
            $events[] = new OrderDeliveryBasicLoadedEvent($this->orderStates->getOrderDeliveries(), $this->context);
        }
        if ($this->orderStates->getTranslations()->count() > 0) {
            $events[] = new OrderStateTranslationBasicLoadedEvent($this->orderStates->getTranslations(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
