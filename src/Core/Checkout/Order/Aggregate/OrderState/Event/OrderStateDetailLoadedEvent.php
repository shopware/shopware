<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderState\Event;

use Shopware\Core\Checkout\Order\Aggregate\OrderState\Collection\OrderStateDetailCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderStateTranslation\Event\OrderStateTranslationBasicLoadedEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;

class OrderStateDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'order_state.detail.loaded';

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var \Shopware\Core\Checkout\Order\Aggregate\OrderState\Collection\OrderStateDetailCollection
     */
    protected $orderStates;

    public function __construct(OrderStateDetailCollection $orderStates, Context $context)
    {
        $this->context = $context;
        $this->orderStates = $orderStates;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
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
        if ($this->orderStates->getTranslations()->count() > 0) {
            $events[] = new OrderStateTranslationBasicLoadedEvent($this->orderStates->getTranslations(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
