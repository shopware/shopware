<?php declare(strict_types=1);

namespace Shopware\OrderDelivery\Event;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\OrderDelivery\Struct\OrderDeliveryDetailCollection;
use Shopware\OrderDeliveryPosition\Event\OrderDeliveryPositionBasicLoadedEvent;

class OrderDeliveryDetailLoadedEvent extends NestedEvent
{
    const NAME = 'orderDelivery.detail.loaded';

    /**
     * @var OrderDeliveryDetailCollection
     */
    protected $orderDeliveries;

    /**
     * @var TranslationContext
     */
    protected $context;

    public function __construct(OrderDeliveryDetailCollection $orderDeliveries, TranslationContext $context)
    {
        $this->orderDeliveries = $orderDeliveries;
        $this->context = $context;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getOrderDeliveries(): OrderDeliveryDetailCollection
    {
        return $this->orderDeliveries;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [
            new OrderDeliveryBasicLoadedEvent($this->orderDeliveries, $this->context),
        ];

        if ($this->orderDeliveries->getPositions()->count() > 0) {
            $events[] = new OrderDeliveryPositionBasicLoadedEvent($this->orderDeliveries->getPositions(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
