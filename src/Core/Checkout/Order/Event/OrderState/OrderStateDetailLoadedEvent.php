<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Event\OrderState;

use Shopware\Checkout\Order\Collection\OrderStateDetailCollection;
use Shopware\Checkout\Order\Event\OrderStateTranslation\OrderStateTranslationBasicLoadedEvent;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class OrderStateDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'order_state.detail.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var OrderStateDetailCollection
     */
    protected $orderStates;

    public function __construct(OrderStateDetailCollection $orderStates, ApplicationContext $context)
    {
        $this->context = $context;
        $this->orderStates = $orderStates;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
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
