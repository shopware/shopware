<?php declare(strict_types=1);

namespace Shopware\OrderState\Event;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\OrderState\Struct\OrderStateBasicCollection;

class OrderStateBasicLoadedEvent extends NestedEvent
{
    const NAME = 'orderState.basic.loaded';

    /**
     * @var OrderStateBasicCollection
     */
    protected $orderStates;

    /**
     * @var TranslationContext
     */
    protected $context;

    public function __construct(OrderStateBasicCollection $orderStates, TranslationContext $context)
    {
        $this->orderStates = $orderStates;
        $this->context = $context;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getOrderStates(): OrderStateBasicCollection
    {
        return $this->orderStates;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];

        return new NestedEventCollection($events);
    }
}
