<?php declare(strict_types=1);

namespace Shopware\Order\Event;

use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class OrderWrittenEvent extends NestedEvent
{
    const NAME = 'order.written';

    /**
     * @var string[]
     */
    private $orderUuids;

    /**
     * @var NestedEventCollection
     */
    private $events;

    /**
     * @var array
     */
    private $errors;

    public function __construct(array $orderUuids, array $errors = [])
    {
        $this->orderUuids = $orderUuids;
        $this->events = new NestedEventCollection();
        $this->errors = $errors;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    /**
     * @return string[]
     */
    public function getOrderUuids(): array
    {
        return $this->orderUuids;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }

    public function addEvent(NestedEvent $event): void
    {
        $this->events->add($event);
    }
    
    public function getEvents(): NestedEventCollection
    {
        return $this->events;
    }
}